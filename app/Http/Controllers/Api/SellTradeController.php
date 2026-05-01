<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Services\SellTradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SellTradeController extends Controller
{
    public function __construct(private readonly SellTradeService $sellTrades)
    {
    }

    /**
     * Active sell trade for the authenticated wallet (A1 gate).
     * Returns has_active + minimal trade info so the UI can disable
     * "Sell" button + link the user back to the in-flight trade.
     */
    public function active(Request $request): JsonResponse
    {
        $wallet = strtolower($request->merchant->wallet_address);

        $trade = Trade::query()
            ->where('seller_wallet', $wallet)
            ->where('type', TradeType::Sell)
            ->whereIn('status', TradeStatus::activeSellStatuses())
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'has_active' => (bool) $trade,
                'trade_hash' => $trade?->trade_hash,
                'status' => $trade?->status?->value,
                'amount_usdc' => $trade?->amount_usdc,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_wallet' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method_id' => ['required', 'integer', 'exists:merchant_payment_methods,id'],
            'fiat_rate' => ['required', 'numeric', 'min:0.000001'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_cash_trade' => ['sometimes', 'boolean'],
            'meeting_location' => ['nullable', 'string', 'max:255'],
            'entry_path' => ['nullable', 'in:merchant_page,private_link'],
        ]);

        $merchant = \App\Models\Merchant::query()
            ->whereRaw('LOWER(wallet_address) = ?', [strtolower($validated['merchant_wallet'])])
            ->first();

        if (! $merchant) {
            return response()->json(['message' => __('p2p.merchant_not_found')], 404);
        }

        try {
            $payload = $this->sellTrades->openTrade($merchant, $validated, $request->merchant->wallet_address);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'trade_hash' => $payload['trade_hash'],
                'trade_id' => $payload['trade_id'],
                'calldata' => $payload['calldata'],
                'escrow_address' => $payload['escrow_address'],
                'approve_amount' => $payload['approve_amount'],
                'expires_at' => $payload['expires_at'],
                'stake_required' => $payload['stake_required'],
                'stake_amount_usdc' => $payload['stake_amount_usdc'],
            ],
            'message' => __('p2p.sell_trade_payload_built'),
        ], 201);
    }

    public function confirmFund(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'fund_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        // B12: only seller may confirm their own fund tx.
        if (! $this->callerIsSeller($request, $trade)) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        try {
            $trade = $this->sellTrades->confirmFund($trade, $validated['fund_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_funded'),
        ]);
    }

    public function show(Request $request, string $tradeHash): JsonResponse
    {
        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_loaded'),
        ]);
    }

    public function confirmJoin(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'join_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        // B12: only buyer may confirm their own join tx.
        if (! $this->callerIsBuyer($request, $trade)) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        try {
            $trade = $this->sellTrades->confirmJoin($trade, $request->merchant, $validated['join_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_joined'),
        ]);
    }

    public function confirmMarkPaid(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'mark_paid_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        // B12: only buyer may mark paid (they're the one sending fiat).
        if (! $this->callerIsBuyer($request, $trade)) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        try {
            $trade = $this->sellTrades->confirmMarkPaid($trade, $request->merchant, $validated['mark_paid_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_payment_marked'),
        ]);
    }

    public function cashProof(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->attachCashProof($trade, $validated['proof'], $validated['note'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_cash_proof_uploaded'),
        ]);
    }

    /**
     * A4: buyer uploads payment proof image/PDF.
     */
    public function paymentProof(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->attachPaymentProof($trade, $request->merchant, $validated['proof']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_payment_proof_uploaded'),
        ]);
    }

    /**
     * A5: list messages for a trade (both parties).
     */
    public function listMessages(Request $request, string $tradeHash): JsonResponse
    {
        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        $callerWallet = strtolower($request->merchant->wallet_address);
        $isParty = $callerWallet === strtolower($trade->seller_wallet)
            || $callerWallet === strtolower($trade->buyer_wallet);

        if (! $isParty) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        // 200-message cap protects polling clients from runaway payloads.
        $messages = $trade->messages()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($m) {
                $att = null;
                if (! empty($m->attachment_path)) {
                    $ext = strtolower(pathinfo($m->attachment_path, PATHINFO_EXTENSION));
                    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $size = 0;
                    try {
                        $size = (int) \Illuminate\Support\Facades\Storage::disk('local')->size($m->attachment_path);
                    } catch (\Throwable) {
                        $size = 0;
                    }
                    $att = [
                        'extension' => $ext,
                        'kind' => in_array($ext, $imageExts) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'file'),
                        'size_bytes' => $size,
                        'filename' => 'trade-msg-' . $m->id . '.' . $ext,
                    ];
                }
                return [
                    'id' => $m->id,
                    'sender_wallet' => $m->sender_wallet,
                    'sender_role' => $m->sender_role,
                    'body' => $m->body,
                    'has_attachment' => ! empty($m->attachment_path),
                    'attachment' => $att,
                    'created_at' => $m->created_at?->toIso8601String(),
                ];
            });

        $isLocked = in_array($trade->status, [
            \App\Enums\TradeStatus::Completed,
            \App\Enums\TradeStatus::Cancelled,
            \App\Enums\TradeStatus::Expired,
            \App\Enums\TradeStatus::Resolved,
        ], true);

        return response()->json([
            'data' => [
                'messages' => $messages,
                'locked' => $isLocked,
            ],
        ]);
    }

    /**
     * A5: send a message (text + optional image).
     */
    public function sendMessage(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $message = $this->sellTrades->postMessage(
                $trade,
                $request->merchant,
                $validated['body'] ?? null,
                $validated['attachment'] ?? null,
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $message->id,
                'sender_wallet' => $message->sender_wallet,
                'sender_role' => $message->sender_role,
                'body' => $message->body,
                'has_attachment' => ! empty($message->attachment_path),
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * A5: stream message attachment. Both parties authorized.
     */
    public function downloadMessageAttachment(Request $request, string $tradeHash, int $messageId)
    {
        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        $callerWallet = strtolower($request->merchant->wallet_address);
        $isParty = $callerWallet === strtolower($trade->seller_wallet)
            || $callerWallet === strtolower($trade->buyer_wallet);

        if (! $isParty) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        $message = $trade->messages()->find($messageId);
        if (! $message || ! $message->attachment_path) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')
            ->download($message->attachment_path, 'trade-msg-' . $message->id . '.' . pathinfo($message->attachment_path, PATHINFO_EXTENSION));
    }

    /**
     * A7: stream the cash proof file (in-person/NFT cash trades).
     * Both parties (seller + buyer) authorized.
     */
    public function downloadCashProof(Request $request, string $tradeHash)
    {
        $trade = $this->findTrade($tradeHash);
        if (! $trade || ! $trade->cash_proof_url) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        $callerWallet = strtolower($request->merchant->wallet_address);
        $isParty = $callerWallet === strtolower($trade->seller_wallet)
            || $callerWallet === strtolower($trade->buyer_wallet);

        if (! $isParty) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')
            ->download($trade->cash_proof_url, 'cash-proof-' . substr($trade->trade_hash, 0, 10) . '.' . pathinfo($trade->cash_proof_url, PATHINFO_EXTENSION));
    }

    /**
     * A4: stream the proof file. Both parties (seller + buyer) authorized.
     */
    public function downloadPaymentProof(Request $request, string $tradeHash)
    {
        $trade = $this->findTrade($tradeHash);
        if (! $trade || ! $trade->payment_proof_url) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        $callerWallet = strtolower($request->merchant->wallet_address);
        $isParty = $callerWallet === strtolower($trade->seller_wallet)
            || $callerWallet === strtolower($trade->buyer_wallet);

        if (! $isParty) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')
            ->download($trade->payment_proof_url, 'payment-proof-' . substr($trade->trade_hash, 0, 10) . '.' . pathinfo($trade->payment_proof_url, PATHINFO_EXTENSION));
    }

    public function verifyPayment(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->setSellerVerifiedPayment($trade, $request->merchant, $validated['verified']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_payment_verified'),
        ]);
    }

    public function confirmRelease(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'release_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        // B12: only seller may confirm their own release tx.
        if (! $this->callerIsSeller($request, $trade)) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        try {
            $trade = $this->sellTrades->confirmRelease($trade, $validated['release_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_released'),
        ]);
    }

    public function openDispute(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'dispute_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        // B12: only seller or buyer may open a dispute.
        if (! $this->callerIsParty($request, $trade)) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        try {
            $trade = $this->sellTrades->openDispute($trade, $request->merchant, $validated['dispute_tx_hash'], $validated['reason']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_dispute_opened'),
        ]);
    }

    public function cancel(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'cancel_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        if (! $this->callerIsParty($request, $trade)) {
            return response()->json(['message' => __('p2p.not_authorized')], 403);
        }

        try {
            $trade = $this->sellTrades->cancel($trade, $validated['cancel_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_cancelled'),
        ]);
    }

    // B12: shared authz helpers — controller-layer guard before service receipt check.
    private function callerIsSeller(Request $request, Trade $trade): bool
    {
        return strtolower($request->merchant->wallet_address) === strtolower((string) $trade->seller_wallet);
    }

    private function callerIsBuyer(Request $request, Trade $trade): bool
    {
        return strtolower($request->merchant->wallet_address) === strtolower((string) $trade->buyer_wallet);
    }

    private function callerIsParty(Request $request, Trade $trade): bool
    {
        return $this->callerIsSeller($request, $trade) || $this->callerIsBuyer($request, $trade);
    }

    private function findTrade(string $tradeHash): ?Trade
    {
        return Trade::query()
            ->where('trade_hash', $tradeHash)
            ->where('type', \App\Enums\TradeType::Sell)
            ->with(['merchant'])
            ->first();
    }

    private function serialize(Trade $trade, Request $request): array
    {
        $callerWallet = strtolower($request->merchant?->wallet_address ?? '');

        // Resolve payment_method ID → human label + type for UI display
        $paymentMethodInfo = $this->resolvePaymentMethod($trade);

        return [
            'trade_hash' => $trade->trade_hash,
            'type' => $trade->type->value,
            'status' => $trade->status->value,
            'seller_wallet' => $trade->seller_wallet,
            'buyer_wallet' => $trade->buyer_wallet,
            'merchant' => [
                'id' => $trade->merchant?->id,
                'username' => $trade->merchant?->username,
                'wallet_address' => $trade->merchant?->wallet_address,
            ],
            'amount_usdc' => $trade->amount_usdc,
            'amount_fiat' => $trade->amount_fiat,
            'currency_code' => $trade->currency_code,
            'exchange_rate' => $trade->exchange_rate,
            'fee_amount' => $trade->fee_amount,
            'payment_method' => $trade->payment_method,
            'payment_method_label' => $paymentMethodInfo['label'],
            'payment_method_type' => $paymentMethodInfo['type'],
            'payment_method_provider' => $paymentMethodInfo['provider'] ?? null,
            'payment_method_details' => $paymentMethodInfo['details'] ?? null,
            'payment_method_safety_note' => $paymentMethodInfo['safety_note'] ?? null,
            'payment_method_location' => $paymentMethodInfo['location'] ?? null,
            'is_cash_trade' => (bool) $trade->is_cash_trade,
            'has_cash_proof' => ! empty($trade->cash_proof_url),
            'cash_proof_url' => $trade->cash_proof_url, // legacy; UI uses download endpoint
            'payment_proof_uploaded_at' => $trade->payment_proof_uploaded_at?->toIso8601String(),
            'has_payment_proof' => ! empty($trade->payment_proof_url),
            'meeting_location' => $trade->meeting_location,
            'nft_token_id' => $trade->nft_token_id,
            'seller_verified_payment' => (bool) $trade->seller_verified_payment,
            'fund_tx_hash' => $trade->fund_tx_hash,
            'join_tx_hash' => $trade->join_tx_hash,
            'mark_paid_tx_hash' => $trade->mark_paid_tx_hash,
            'release_tx_hash' => $trade->release_tx_hash,
            'cancel_tx_hash' => $trade->cancel_tx_hash,
            'dispute_tx_hash' => $trade->dispute_tx_hash,
            'expires_at' => $trade->expires_at?->toIso8601String(),
            'completed_at' => $trade->completed_at?->toIso8601String(),
            'is_seller' => $callerWallet !== '' && $callerWallet === strtolower((string) $trade->seller_wallet),
            'is_merchant' => $callerWallet !== '' && $callerWallet === strtolower((string) $trade->buyer_wallet),
            'stake_amount' => $trade->stake_amount,
        ];
    }

    /**
     * A6: expose full payment instructions so the buyer sees account details
     * (bank/online/cash) — not just a generic label.
     */
    private function resolvePaymentMethod(Trade $trade): array
    {
        $id = is_numeric($trade->payment_method) ? (int) $trade->payment_method : null;
        if (! $id) {
            return [
                'label' => $trade->payment_method,
                'type' => $trade->payment_method,
                'provider' => null,
                'details' => null,
                'safety_note' => null,
                'location' => null,
            ];
        }

        $pm = \App\Models\MerchantPaymentMethod::find($id);
        if (! $pm) {
            return [
                'label' => 'Unknown payment method',
                'type' => null,
                'provider' => null,
                'details' => null,
                'safety_note' => null,
                'location' => null,
            ];
        }

        return [
            'label' => $pm->label ?: $pm->type?->value,
            'type' => $pm->type?->value,
            'provider' => $pm->provider,
            // details is a JSON dict with account_name/number/bank/swift/iban/handle/etc.
            'details' => $pm->details ?: null,
            'safety_note' => $pm->safety_note,
            'location' => $pm->location,
        ];
    }
}
