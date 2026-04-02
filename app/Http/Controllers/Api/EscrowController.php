<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BlockchainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EscrowController extends Controller
{
    public function __construct(
        private readonly BlockchainService $blockchain,
    ) {}

    /**
     * POST /api/merchant/escrow/deposit
     * Client must have already approved escrow contract for the amount.
     */
    public function deposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $merchant = $request->merchant;
        $rawAmount = $this->blockchain->humanToUsdc((string) $validated['amount']);

        // Verify on-chain allowance before calling depositEscrow
        try {
            $allowance = $this->blockchain->getUsdcAllowance($merchant->wallet_address);
            if (bccomp($allowance, $rawAmount) < 0) {
                return response()->json([
                    'message' => __('p2p.insufficient_usdc_allowance'),
                ], 422);
            }
            $txHash = $this->blockchain->depositEscrow($merchant->wallet_address, $rawAmount);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Escrow operation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('p2p.server_error')], 503);
        }

        return response()->json([
            'data'    => ['tx_hash' => $txHash],
            'message' => __('p2p.escrow_deposit_submitted'),
        ]);
    }

    /**
     * POST /api/merchant/escrow/withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $merchant = $request->merchant;
        $rawAmount = $this->blockchain->humanToUsdc((string) $validated['amount']);

        // Verify on-chain available balance
        try {
            $available = $this->blockchain->getAvailableBalance($merchant->wallet_address);
            $availableDecimal = $this->blockchain->hexToDecimal($available);
            if (bccomp($availableDecimal, $rawAmount) < 0) {
                return response()->json([
                    'message' => __('p2p.insufficient_escrow_balance'),
                ], 422);
            }
            $txHash = $this->blockchain->withdrawEscrow($merchant->wallet_address, $rawAmount);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Escrow operation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('p2p.server_error')], 503);
        }

        return response()->json([
            'data'    => ['tx_hash' => $txHash],
            'message' => __('p2p.escrow_withdraw_submitted'),
        ]);
    }

    /**
     * GET /api/merchant/escrow/tx/{hash}
     * Poll for transaction receipt.
     */
    public function txStatus(string $hash): JsonResponse
    {
        try {
            $receipt = $this->blockchain->getTransactionReceipt($hash);
        } catch (\RuntimeException $e) {
            return response()->json(['data' => ['status' => 'pending']]);
        }

        if ($receipt === null) {
            return response()->json(['data' => ['status' => 'pending']]);
        }

        $confirmed = ($receipt['status'] ?? '') === '0x1';

        return response()->json([
            'data' => [
                'status'       => $confirmed ? 'confirmed' : 'failed',
                'block_number' => hexdec($receipt['blockNumber'] ?? '0x0'),
            ],
        ]);
    }


}
