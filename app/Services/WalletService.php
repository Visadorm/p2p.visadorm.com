<?php

namespace App\Services;

class WalletService
{
    public function __construct(
        private readonly BlockchainService $blockchain,
    ) {}

    public function getUsdcBalance(string $address): string
    {
        return $this->blockchain->getUsdcBalance($address);
    }

    public function estimateGas(array $tx): string
    {
        return $this->blockchain->estimateGas($tx);
    }

    public function getTxReceipt(string $txHash): ?array
    {
        return $this->blockchain->getTransactionReceipt($txHash);
    }
}
