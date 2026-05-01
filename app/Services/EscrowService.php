<?php

namespace App\Services;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\Trade;
use Illuminate\Support\Facades\Log;

class EscrowService
{
    /**
     * Active trade statuses that lock escrow funds.
     */
    private const array ACTIVE_STATUSES = [
        TradeStatus::Pending,
        TradeStatus::EscrowLocked,
        TradeStatus::PaymentSent,
        TradeStatus::Disputed,
    ];

    public function __construct(
        private readonly BlockchainService $blockchainService,
    ) {}

    /**
     * Get the merchant's TOTAL escrow balance (deposited, before subtracting locked).
     *
     * Tries on-chain merchantEscrowBalance first. Falls back to DB total_volume.
     */
    public function getMerchantTotalEscrow(Merchant $merchant): float
    {
        try {
            if (! empty($merchant->wallet_address)) {
                $rawBalance = $this->blockchainService->getMerchantEscrowBalance($merchant->wallet_address);
                $decimal = $this->hexToDecimal($rawBalance);

                return (float) $this->blockchainService->usdcToHuman($decimal);
            }
        } catch (\Throwable) {
            // Contract not deployed or RPC unreachable
        }

        // Fallback: DB total_volume as proxy
        return (float) $merchant->total_volume;
    }

    /**
     * Get the merchant's AVAILABLE escrow balance (total minus locked in trades).
     *
     * This is the amount available for new trades or withdrawal.
     */
    public function getMerchantAvailableBalance(Merchant $merchant): float
    {
        return max($this->getMerchantTotalEscrow($merchant) - $this->getLockedInTrades($merchant), 0);
    }

    /**
     * A10: only BUY trades lock the merchant's deposited escrow. Sell trades
     * use the seller's wallet USDC (TradeEscrow contract holds it from the
     * seller side, not from merchant escrow). Summing both was the source of
     * the "$980 instead of $1000" bug — a separate sell-flow dispute was
     * being subtracted from the merchant's available balance.
     */
    public function getLockedInTrades(Merchant $merchant): float
    {
        return (float) Trade::where('merchant_id', $merchant->id)
            ->where('type', TradeType::Buy)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->sum('amount_usdc');
    }

    /**
     * Check whether a merchant can initiate a trade for the given amount.
     *
     * B6: prefer on-chain authoritative balance and only fall back to DB
     * derivation when the RPC is unreachable. Both must clear the amount.
     */
    public function canInitiateTrade(Merchant $merchant, float $amount): bool
    {
        $dbOk = $this->getMerchantAvailableBalance($merchant) >= $amount;

        try {
            if (! empty($merchant->wallet_address)) {
                $rawAvailable = $this->blockchainService->getAvailableBalance($merchant->wallet_address);
                $chainAvailable = (float) $this->blockchainService->usdcToHuman($this->hexToDecimal($rawAvailable));

                // Log divergence proactively so admins notice mismatch.
                $dbAvailable = $this->getMerchantAvailableBalance($merchant);
                if (abs($dbAvailable - $chainAvailable) > 0.01) {
                    Log::warning('canInitiateTrade balance divergence', [
                        'merchant_id' => $merchant->id,
                        'db' => $dbAvailable,
                        'chain' => $chainAvailable,
                        'requested' => $amount,
                    ]);
                }

                return $chainAvailable >= $amount && $dbOk;
            }
        } catch (\Throwable) {
            // RPC unreachable — fall back to DB-only check.
        }

        return $dbOk;
    }

    /**
     * A10: reconcile DB-derived available balance vs on-chain authoritative.
     * Returns ['db' => float, 'chain' => float, 'divergence' => float, 'ok' => bool].
     * Logs a warning when divergence > $tolerance USDC.
     */
    public function reconcileBalance(Merchant $merchant, float $tolerance = 0.01): array
    {
        $dbAvailable = $this->getMerchantAvailableBalance($merchant);

        $chainAvailable = $dbAvailable; // fallback if RPC fails
        try {
            if (! empty($merchant->wallet_address)) {
                $rawAvailable = $this->blockchainService->getAvailableBalance($merchant->wallet_address);
                $decimal = $this->hexToDecimal($rawAvailable);
                $chainAvailable = (float) $this->blockchainService->usdcToHuman($decimal);
            }
        } catch (\Throwable) {
            // RPC unavailable — return DB-only assessment with ok=true (no divergence detectable).
            return ['db' => $dbAvailable, 'chain' => null, 'divergence' => null, 'ok' => true];
        }

        $divergence = abs($dbAvailable - $chainAvailable);
        $ok = $divergence <= $tolerance;

        if (! $ok) {
            Log::warning('Escrow balance divergence detected', [
                'merchant_id' => $merchant->id,
                'wallet' => $merchant->wallet_address,
                'db_available' => $dbAvailable,
                'chain_available' => $chainAvailable,
                'divergence_usdc' => $divergence,
            ]);
        }

        return [
            'db' => $dbAvailable,
            'chain' => $chainAvailable,
            'divergence' => $divergence,
            'ok' => $ok,
        ];
    }

    /**
     * Convert a hex string (from an RPC response) to a decimal string.
     */
    private function hexToDecimal(string $hex): string
    {
        $hex = str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
        $hex = ltrim($hex, '0') ?: '0';

        if (strlen($hex) < 16) {
            return (string) hexdec($hex);
        }

        return $this->bigHexToDecimal($hex);
    }

    private function bigHexToDecimal(string $hex): string
    {
        $decimal = '0';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $decimal = bcmul($decimal, '16');
            $decimal = bcadd($decimal, (string) hexdec($hex[$i]));
        }

        return $decimal;
    }
}
