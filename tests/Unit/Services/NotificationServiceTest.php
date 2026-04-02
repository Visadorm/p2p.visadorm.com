<?php

namespace Tests\Unit\Services;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\P2pNotification;
use App\Models\Trade;
use App\Services\NotificationService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);

        $this->service = new NotificationService;
    }

    private function createMerchant(array $overrides = []): Merchant
    {
        $rank = MerchantRank::where('slug', 'new-member')->first();

        return Merchant::create(array_merge([
            'wallet_address' => '0x' . fake()->sha1(),
            'username' => 'user_' . fake()->unique()->word(),
            'is_active' => true,
            'total_trades' => 0,
            'completion_rate' => 0,
            'total_volume' => 0,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ], $overrides));
    }

    public function test_create_notification(): void
    {
        $merchant = $this->createMerchant();

        $notification = $this->service->create(
            $merchant,
            'trade_initiated',
            'New Trade',
            'A new trade has been initiated.',
        );

        $this->assertInstanceOf(P2pNotification::class, $notification);
        $this->assertSame($merchant->id, $notification->merchant_id);
        $this->assertSame('trade_initiated', $notification->type);
        $this->assertSame('New Trade', $notification->title);
        $this->assertSame('A new trade has been initiated.', $notification->body);
        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->trade_id);
    }

    public function test_create_notification_with_trade_id(): void
    {
        $merchant = $this->createMerchant();

        $trade = Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $merchant->id,
            'buyer_wallet' => '0x' . fake()->sha1(),
            'amount_usdc' => 100,
            'amount_fiat' => 5700,
            'currency_code' => 'DOP',
            'exchange_rate' => 57.0,
            'fee_amount' => 0.2,
            'payment_method' => 'bank_transfer',
            'type' => TradeType::Buy,
            'status' => TradeStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ]);

        $notification = $this->service->create(
            $merchant,
            'payment_sent',
            'Payment Sent',
            'Buyer marked payment as sent.',
            tradeId: $trade->id,
        );

        $this->assertSame($trade->id, $notification->trade_id);
    }

    public function test_mark_as_read(): void
    {
        $merchant = $this->createMerchant();

        $notification = $this->service->create(
            $merchant,
            'trade_completed',
            'Trade Completed',
            'Trade has been completed.',
        );

        $this->assertFalse($notification->is_read);

        $this->service->markAsRead($notification);

        $notification->refresh();
        $this->assertTrue($notification->is_read);
    }

    public function test_mark_all_read(): void
    {
        $merchant = $this->createMerchant();

        $this->service->create($merchant, 'type_a', 'Title A', 'Body A');
        $this->service->create($merchant, 'type_b', 'Title B', 'Body B');
        $this->service->create($merchant, 'type_c', 'Title C', 'Body C');

        $this->assertSame(3, $this->service->getUnreadCount($merchant));

        $this->service->markAllRead($merchant);

        $this->assertSame(0, $this->service->getUnreadCount($merchant));
    }

    public function test_mark_all_read_does_not_affect_other_merchants(): void
    {
        $merchant = $this->createMerchant();
        $otherMerchant = $this->createMerchant();

        $this->service->create($merchant, 'type_a', 'Title A', 'Body A');
        $this->service->create($otherMerchant, 'type_b', 'Title B', 'Body B');

        $this->service->markAllRead($merchant);

        $this->assertSame(0, $this->service->getUnreadCount($merchant));
        $this->assertSame(1, $this->service->getUnreadCount($otherMerchant));
    }

    public function test_get_unread_count(): void
    {
        $merchant = $this->createMerchant();

        $this->assertSame(0, $this->service->getUnreadCount($merchant));

        $n1 = $this->service->create($merchant, 'type_a', 'Title A', 'Body A');
        $n2 = $this->service->create($merchant, 'type_b', 'Title B', 'Body B');

        $this->assertSame(2, $this->service->getUnreadCount($merchant));

        $this->service->markAsRead($n1);

        $this->assertSame(1, $this->service->getUnreadCount($merchant));
    }

    public function test_get_unread_count_excludes_read_notifications(): void
    {
        $merchant = $this->createMerchant();

        $n1 = $this->service->create($merchant, 'type_a', 'Title A', 'Body A');
        $this->service->markAsRead($n1);

        $this->service->create($merchant, 'type_b', 'Title B', 'Body B');

        $this->assertSame(1, $this->service->getUnreadCount($merchant));
    }
}
