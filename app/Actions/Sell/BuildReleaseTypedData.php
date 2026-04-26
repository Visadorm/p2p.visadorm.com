<?php

declare(strict_types=1);

namespace App\Actions\Sell;

use App\Models\Trade;
use App\Settings\BlockchainSettings;

class BuildReleaseTypedData
{
    public function __construct(private readonly BlockchainSettings $blockchain) {}

    public function execute(Trade $trade, int $sellerNonce, int $deadlineSeconds = 600): array
    {
        $deadline = now()->addSeconds($deadlineSeconds)->timestamp;

        return [
            'domain' => [
                'name' => 'VisadormP2P',
                'version' => '1',
                'chainId' => $this->blockchain->chain_id,
                'verifyingContract' => $this->blockchain->trade_escrow_address,
            ],
            'types' => [
                'ReleaseSellEscrow' => [
                    ['name' => 'tradeId', 'type' => 'bytes32'],
                    ['name' => 'nonce', 'type' => 'uint256'],
                    ['name' => 'deadline', 'type' => 'uint256'],
                ],
            ],
            'primaryType' => 'ReleaseSellEscrow',
            'message' => [
                'tradeId' => $this->normalizeTradeId($trade->trade_hash),
                'nonce' => $sellerNonce,
                'deadline' => $deadline,
            ],
            'meta' => [
                'trade_hash' => $trade->trade_hash,
                'amount_usdc' => (string) $trade->amount_usdc,
                'buyer_wallet' => $trade->buyer_wallet,
                'deadline_iso' => now()->addSeconds($deadlineSeconds)->toIso8601String(),
            ],
        ];
    }

    private function normalizeTradeId(string $tradeHash): string
    {
        $clean = str_starts_with($tradeHash, '0x') ? substr($tradeHash, 2) : $tradeHash;
        if (strlen($clean) === 64) {
            return '0x' . strtolower($clean);
        }
        return '0x' . str_pad(bin2hex($tradeHash), 64, '0', STR_PAD_LEFT);
    }
}
