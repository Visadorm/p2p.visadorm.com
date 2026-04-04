<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Models\Dispute;
use App\Models\Trade;
use App\Services\BlockchainService;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeService $disputeService,
        private readonly BlockchainService $blockchainService,
    ) {}

    /**
     * Open a dispute on a trade.
     */
    public function store(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        $userWallet = strtolower($request->user()->wallet_address);
        $isBuyer = strtolower($trade->buyer_wallet) === $userWallet;
        $isMerchant = $trade->merchant_id === $request->merchant->id;

        if (! $isBuyer && ! $isMerchant) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent])) {
            return response()->json([
                'message' => __('p2p.trade_invalid_status'),
            ], 422);
        }

        if ($trade->dispute()->exists()) {
            return response()->json([
                'message' => __('p2p.dispute_already_exists'),
            ], 422);
        }

        $dispute = $this->disputeService->openDispute($trade, $userWallet, $validated['reason']);

        try {
            $txHash = $this->blockchainService->openDispute($trade->trade_hash, $userWallet);
            $dispute->update(['resolution_tx_hash' => $txHash]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('openDispute blockchain error', [
                'trade_hash' => $trade->trade_hash,
                'error'      => $e->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $dispute,
            'message' => __('p2p.dispute_created'),
        ], 201);
    }

    /**
     * View dispute details.
     */
    public function show(Request $request, int $disputeId): JsonResponse
    {
        $dispute = Dispute::with('trade')->find($disputeId);

        if (! $dispute) {
            return response()->json([
                'message' => __('p2p.dispute_not_found'),
            ], 404);
        }

        $userWallet = strtolower($request->user()->wallet_address);
        $trade = $dispute->trade;
        $isBuyer = strtolower($trade->buyer_wallet) === $userWallet;
        $isMerchant = $trade->merchant_id === $request->merchant->id;

        if (! $isBuyer && ! $isMerchant) {
            return response()->json([
                'message' => __('p2p.dispute_not_authorized'),
            ], 403);
        }

        $dispute->load('trade.merchant:id,username,wallet_address,rank_id');

        return response()->json([
            'data' => $dispute,
            'message' => __('p2p.dispute_loaded'),
        ]);
    }

    /**
     * Upload evidence for an open dispute.
     */
    public function uploadEvidence(Request $request, int $disputeId): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,mp4,webm'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $dispute = Dispute::with('trade')->find($disputeId);

        if (! $dispute) {
            return response()->json([
                'message' => __('p2p.dispute_not_found'),
            ], 404);
        }

        $userWallet = strtolower($request->user()->wallet_address);
        $trade = $dispute->trade;
        $isBuyer = strtolower($trade->buyer_wallet) === $userWallet;
        $isMerchant = $trade->merchant_id === $request->merchant->id;

        if (! $isBuyer && ! $isMerchant) {
            return response()->json([
                'message' => __('p2p.dispute_not_authorized'),
            ], 403);
        }

        if ($dispute->status !== DisputeStatus::Open) {
            return response()->json([
                'message' => __('p2p.dispute_not_open'),
            ], 422);
        }

        $dispute = $this->disputeService->submitEvidence($dispute, $request->file('file'), $userWallet, $request->input('note'));

        return response()->json([
            'data' => $dispute,
            'message' => __('p2p.dispute_evidence_uploaded'),
        ]);
    }

    /**
     * POST /api/admin/dispute/{disputeId}/resolve
     * Admin only. Uses ADMIN_ROLE key to resolve on-chain.
     */
    public function resolve(Request $request, int $disputeId): JsonResponse
    {
        // Admin-only: check user has an admin role
        if (! in_array($request->user()?->role, ['super_admin', 'dispute_manager'])) {
            return response()->json(['message' => __('p2p.forbidden')], 403);
        }

        $validated = $request->validate([
            'winner' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
        ]);

        $dispute = Dispute::with('trade.merchant')->find($disputeId);

        if (! $dispute) {
            return response()->json(['message' => __('p2p.dispute_not_found')], 404);
        }

        if ($dispute->status !== DisputeStatus::Open) {
            return response()->json(['message' => __('p2p.dispute_not_open')], 422);
        }

        $trade = $dispute->trade;
        $winner = strtolower($validated['winner']);
        $merchantWallet = strtolower($trade->merchant->wallet_address);
        $buyerWallet = strtolower($trade->buyer_wallet);

        if ($winner !== $merchantWallet && $winner !== $buyerWallet) {
            return response()->json(['message' => __('p2p.dispute_winner_must_be_party')], 422);
        }

        try {
            $txHash = $this->blockchainService->resolveDispute($trade->trade_hash, $validated['winner']);
            $this->disputeService->resolveDispute($dispute, $validated['winner'], $txHash);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('resolveDispute blockchain error', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => __('p2p.server_error'),
            ], 503);
        }

        // Burn NFT separately — failure here is non-fatal (dispute is already resolved)
        if ($trade->payment_method === 'cash_meeting' && $trade->nft_token_id) {
            try {
                $this->blockchainService->burnTradeNFT($trade->trade_hash);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('burnTradeNFT after resolve failed', [
                    'trade_hash' => $trade->trade_hash,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['message' => __('p2p.dispute_resolved')]);
    }
}
