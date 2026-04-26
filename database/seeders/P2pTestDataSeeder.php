<?php

namespace Database\Seeders;

use App\Enums\BankProofStatus;
use App\Enums\BuyerVerification;
use App\Enums\DisputeStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Enums\PaymentMethodType;
use App\Enums\StakePaidBy;
use App\Enums\TradingLinkType;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Dispute;
use App\Models\KycDocument;
use App\Models\Merchant;
use App\Models\MerchantCurrency;
use App\Models\MerchantPaymentMethod;
use App\Models\MerchantRank;
use App\Models\MerchantStat;
use App\Models\MerchantTradingLink;
use App\Models\P2pNotification;
use App\Models\Review;
use App\Models\Trade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class P2pTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = MerchantRank::all()->keyBy('slug');
        $currencies = ['DOP', 'EUR', 'USD', 'HTG', 'COP'];
        $paymentMethods = ['bank_transfer', 'wise', 'zelle', 'paypal', 'mobile_payment', 'cash_meeting'];
        $providers = [
            'bank_transfer' => ['Chase Bank', 'Bank of America', 'Banreservas', 'Banco Popular', 'BHD Leon'],
            'wise' => ['Wise'],
            'zelle' => ['Zelle'],
            'paypal' => ['PayPal'],
            'mobile_payment' => ['Apple Pay', 'Google Pay'],
            'cash_meeting' => ['Cash Meeting'],
        ];
        $locations = ['Santo Domingo Central', 'Zona Colonial', 'Miami Beach', 'New York', 'Punta Cana', 'Santiago'];

        // Create 50 merchants
        $merchants = collect();
        for ($i = 1; $i <= 50; $i++) {
            $rankPool = $ranks->values();
            $rank = $rankPool->random();

            $totalTrades = match ($rank->slug) {
                'new-member' => rand(0, 20),
                'junior-member' => rand(21, 100),
                'senior-member' => rand(101, 999),
                'hero-merchant' => rand(1000, 9999),
                'elite-merchant' => rand(10000, 50000),
                default => rand(0, 20),
            };

            $completionRate = match ($rank->slug) {
                'senior-member' => rand(9000, 9999) / 100,
                'hero-merchant' => rand(9500, 9999) / 100,
                'elite-merchant' => rand(9700, 9999) / 100,
                default => rand(7000, 9999) / 100,
            };

            $volume = match ($rank->slug) {
                'hero-merchant' => rand(1000000, 9999999),
                'elite-merchant' => rand(10000000, 99999999),
                default => rand(100, 999999),
            };

            $merchant = Merchant::create([
                'wallet_address' => '0x' . Str::random(40),
                'username' => 'merchant_' . $i,
                'email' => 'merchant' . $i . '@test.com',
                'bio' => 'P2P trader since ' . rand(2020, 2025) . '. Fast and reliable.',
                'rank_id' => $rank->id,
                'is_legendary' => $i <= 2,
                'kyc_status' => collect(KycStatus::cases())->random(),
                'bank_verified' => (bool) rand(0, 1),
                'email_verified' => (bool) rand(0, 1),
                'business_verified' => rand(0, 100) < 30,
                'is_fast_responder' => rand(0, 100) < 60,
                'has_liquidity' => rand(0, 100) < 50,
                'is_active' => rand(0, 100) < 90,
                'is_online' => rand(0, 100) < 40,
                'last_seen_at' => now()->subMinutes(rand(1, 10000)),
                'avg_response_minutes' => rand(1, 30),
                'total_trades' => $totalTrades,
                'total_volume' => $volume,
                'completion_rate' => $completionRate,
                'reliability_score' => rand(50, 100) / 10,
                'dispute_rate' => rand(0, 500) / 100,
                'buyer_verification' => collect(BuyerVerification::cases())->random(),
                'trade_instructions' => "1. Send payment within the trade timer\n2. Include trade ID in reference\n3. Do not mark paid before sending",
                'trade_timer_minutes' => collect([15, 30, 45, 60])->random(),
                'member_since' => now()->subDays(rand(30, 800)),
            ]);

            $merchants->push($merchant);

            // Add 1-4 currencies per merchant
            $merchantCurrencies = collect($currencies)->random(rand(1, 4));
            foreach ($merchantCurrencies as $code) {
                MerchantCurrency::create([
                    'merchant_id' => $merchant->id,
                    'currency_code' => $code,
                    'markup_percent' => rand(0, 500) / 100,
                    'min_amount' => collect([10, 20, 50, 100])->random(),
                    'max_amount' => collect([1000, 5000, 10000, 50000])->random(),
                    'is_active' => true,
                ]);
            }

            // Add 1-3 payment methods per merchant
            $merchantPayments = collect($paymentMethods)->random(rand(1, 3));
            foreach ($merchantPayments as $method) {
                $type = match ($method) {
                    'bank_transfer' => PaymentMethodType::BankTransfer,
                    'cash_meeting' => PaymentMethodType::CashMeeting,
                    'mobile_payment' => PaymentMethodType::MobilePayment,
                    default => PaymentMethodType::OnlinePayment,
                };

                $providerList = $providers[$method] ?? ['Other'];

                MerchantPaymentMethod::create([
                    'merchant_id' => $merchant->id,
                    'type' => $type,
                    'provider' => collect($providerList)->random(),
                    'label' => collect($providerList)->random(),
                    'details' => ['account' => '****' . rand(1000, 9999)],
                    'is_active' => true,
                    'location' => $method === 'cash_meeting' ? collect($locations)->random() : null,
                    'location_lat' => $method === 'cash_meeting' ? rand(180000, 260000) / 10000 : null,
                    'location_lng' => $method === 'cash_meeting' ? rand(-850000, -700000) / 10000 : null,
                    'safety_note' => $method === 'cash_meeting' ? 'Public place only' : null,
                ]);
            }

            // Add trading links (1 public primary + 0-2 private)
            MerchantTradingLink::create([
                'merchant_id' => $merchant->id,
                'slug' => 'merchant-' . $i,
                'type' => TradingLinkType::Public,
                'is_primary' => true,
                'label' => $merchant->username . ' Public',
                'is_active' => true,
            ]);

            for ($j = 0; $j < rand(0, 2); $j++) {
                MerchantTradingLink::create([
                    'merchant_id' => $merchant->id,
                    'slug' => 'priv-' . $i . '-' . Str::random(6),
                    'type' => TradingLinkType::Private,
                    'is_primary' => false,
                    'label' => 'Private Link ' . ($j + 1),
                    'is_active' => true,
                ]);
            }
        }

        // Create 500 trades
        $tradingLinks = MerchantTradingLink::all();
        $tradeStatuses = TradeStatus::cases();

        for ($i = 1; $i <= 500; $i++) {
            $link = $tradingLinks->random();
            $merchant = $merchants->firstWhere('id', $link->merchant_id) ?? $merchants->random();
            $status = $tradeStatuses[array_rand($tradeStatuses)];
            $amountUsdc = rand(10, 50000);
            $rate = rand(5000, 7000) / 100;
            $feeAmount = round($amountUsdc * 0.002, 6);
            $createdAt = now()->subHours(rand(1, 2000));

            $trade = Trade::create([
                'trade_hash' => '0x' . Str::random(64),
                'trading_link_id' => $link->id,
                'merchant_id' => $merchant->id,
                'buyer_wallet' => '0x' . Str::random(40),
                'amount_usdc' => $amountUsdc,
                'amount_fiat' => round($amountUsdc * $rate, 2),
                'currency_code' => collect($currencies)->random(),
                'exchange_rate' => $rate,
                'fee_amount' => $feeAmount,
                'payment_method' => collect($paymentMethods)->random(),
                'type' => collect(TradeType::cases())->random(),
                'status' => $status,
                'stake_amount' => $link->type === TradingLinkType::Public ? 5.0 : 0,
                'stake_paid_by' => $link->type === TradingLinkType::Public ? collect(StakePaidBy::cases())->random() : null,
                'escrow_tx_hash' => '0x' . Str::random(64),
                'release_tx_hash' => $status === TradeStatus::Completed ? '0x' . Str::random(64) : null,
                'bank_proof_status' => in_array($status, [TradeStatus::PaymentSent, TradeStatus::PaymentConfirmed, TradeStatus::Completed])
                    ? collect(BankProofStatus::cases())->random() : null,
                'disputed_at' => $status === TradeStatus::Disputed ? $createdAt->addHours(rand(1, 24)) : null,
                'completed_at' => $status === TradeStatus::Completed ? $createdAt->addHours(rand(1, 48)) : null,
                'expires_at' => $createdAt->addMinutes($merchant->trade_timer_minutes ?? 30),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Add dispute for disputed trades
            if ($status === TradeStatus::Disputed) {
                Dispute::create([
                    'trade_id' => $trade->id,
                    'opened_by' => rand(0, 1) ? $trade->buyer_wallet : $merchant->wallet_address,
                    'reason' => collect([
                        'Payment not received after 2 hours',
                        'Merchant not responding',
                        'Wrong amount sent',
                        'Buyer claims payment sent but no proof',
                        'Cash meeting no-show',
                        'Fraudulent payment receipt',
                    ])->random(),
                    'evidence' => null,
                    'status' => collect(DisputeStatus::cases())->random(),
                ]);
            }

            // Add review for completed trades (70% chance)
            if ($status === TradeStatus::Completed && rand(1, 100) <= 70) {
                Review::create([
                    'trade_id' => $trade->id,
                    'merchant_id' => $merchant->id,
                    'reviewer_wallet' => $trade->buyer_wallet,
                    'rating' => rand(3, 5),
                    'comment' => collect([
                        'Fast response and secure trade. Highly recommended!',
                        'Payment confirmed quickly. Great escrow system.',
                        'Smooth trade, good communication.',
                        'Excellent merchant, very professional.',
                        'Quick and easy trade. Will use again.',
                        'Good experience overall.',
                        'Very reliable, fast payment.',
                        null,
                    ])->random(),
                    'created_at' => $trade->completed_at ?? $createdAt,
                ]);
            }
        }

        // Create KYC documents for merchants with approved/pending KYC
        foreach ($merchants as $merchant) {
            $docCount = rand(1, 4);
            $types = collect(KycDocumentType::cases())->random($docCount);

            foreach ($types as $type) {
                KycDocument::create([
                    'merchant_id' => $merchant->id,
                    'type' => $type,
                    'file_path' => 'kyc/' . Str::random(20) . '.pdf',
                    'original_name' => $type->value . '_' . $merchant->username . '.pdf',
                    'status' => collect(KycStatus::cases())->random(),
                    'reviewed_by' => rand(0, 1) ? 1 : null,
                    'reviewed_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                ]);
            }
        }

        // Create merchant stats (last 30 days for each merchant)
        foreach ($merchants->take(20) as $merchant) {
            for ($d = 29; $d >= 0; $d--) {
                $date = now()->subDays($d)->toDateString();
                MerchantStat::create([
                    'merchant_id' => $merchant->id,
                    'date' => $date,
                    'trades_count' => rand(0, 15),
                    'volume' => rand(0, 50000),
                    'completed_count' => rand(0, 12),
                    'disputed_count' => rand(0, 2),
                    'created_at' => now()->subDays($d),
                ]);
            }
        }

        // Create notifications for merchants
        foreach ($merchants->take(30) as $merchant) {
            $merchantTrades = Trade::where('merchant_id', $merchant->id)->limit(5)->get();

            foreach ($merchantTrades as $trade) {
                P2pNotification::create([
                    'merchant_id' => $merchant->id,
                    'type' => collect(['trade_payment', 'bank_proof', 'buyer_id', 'dispute', 'trade_completed'])->random(),
                    'title' => 'Trade #' . substr($trade->trade_hash, 0, 10),
                    'body' => collect([
                        'Buyer uploaded bank proof',
                        'Buyer submitted ID verification',
                        'Payment marked as sent',
                        'Trade completed successfully',
                        'Dispute opened by buyer',
                    ])->random(),
                    'trade_id' => $trade->id,
                    'is_read' => (bool) rand(0, 1),
                    'created_at' => $trade->created_at,
                ]);
            }
        }
    }
}
