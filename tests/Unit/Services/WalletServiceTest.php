<?php

namespace Tests\Unit\Services;

use App\Services\BlockchainService;
use App\Services\WalletService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    private WalletService $walletService;
    private BlockchainService|MockObject $blockchain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockchain = $this->createMock(BlockchainService::class);
        $this->walletService = new WalletService($this->blockchain);
    }

    public function test_get_usdc_balance_delegates_to_blockchain_service(): void
    {
        $this->blockchain
            ->expects($this->once())
            ->method('getUsdcBalance')
            ->with('0xabc123')
            ->willReturn('1000.50');

        $result = $this->walletService->getUsdcBalance('0xabc123');
        $this->assertSame('1000.50', $result);
    }

    public function test_estimate_gas_delegates_to_blockchain_service(): void
    {
        $tx = ['to' => '0x123', 'value' => '0x0'];
        $this->blockchain
            ->expects($this->once())
            ->method('estimateGas')
            ->with($tx)
            ->willReturn('21000');

        $result = $this->walletService->estimateGas($tx);
        $this->assertSame('21000', $result);
    }

    public function test_get_tx_receipt_delegates_to_blockchain_service(): void
    {
        $txHash = '0xabc';
        $receipt = ['status' => '0x1', 'blockNumber' => '0x1a'];
        $this->blockchain
            ->expects($this->once())
            ->method('getTransactionReceipt')
            ->with($txHash)
            ->willReturn($receipt);

        $result = $this->walletService->getTxReceipt($txHash);
        $this->assertSame($receipt, $result);
    }

    public function test_get_tx_receipt_returns_null_for_pending_tx(): void
    {
        $this->blockchain
            ->expects($this->once())
            ->method('getTransactionReceipt')
            ->willReturn(null);

        $result = $this->walletService->getTxReceipt('0xpending');
        $this->assertNull($result);
    }
}
