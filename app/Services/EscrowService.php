<?php

namespace App\Services;

use App\Enums\TradeStatus;
use App\Models\Merchant;
use App\Models\Trade;

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
     * Get total USDC locked in active trades for a merchant.
     */
    public function getLockedInTrades(Merchant $merchant): float
    {
        return (float) Trade::where('merchant_id', $merchant->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->sum('amount_usdc');
    }

    /**
     * Check whether a merchant can initiate a trade for the given amount.
     */
    public function canInitiateTrade(Merchant $merchant, float $amount): bool
    {
        return $this->getMerchantAvailableBalance($merchant) >= $amount;
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
