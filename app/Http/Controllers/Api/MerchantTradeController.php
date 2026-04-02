<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\TradeStatus;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MerchantTradeController extends Controller
{
    /**
     * List merchant's trades with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $request->validate([
            'role' => ['sometimes', 'string', 'in:merchant,buyer,all'],
            'status' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $role = $request->input('role', 'all');
        $walletAddress = $merchant->wallet_address;

        if ($role === 'buyer') {
            $query = Trade::where('buyer_wallet', $walletAddress)->with(['merchant:id,username,wallet_address', 'tradingLink', 'review']);
        } elseif ($role === 'merchant') {
            $query = $merchant->trades()->with(['tradingLink', 'review']);
        } else {
            // All trades — both as merchant and as buyer
            $query = Trade::where(function ($q) use ($merchant, $walletAddress) {
                $q->where('merchant_id', $merchant->id)
                  ->orWhere('buyer_wallet', $walletAddress);
            })->with(['merchant:id,username,wallet_address', 'tradingLink', 'review']);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('trade_hash', 'LIKE', "%{$search}%")
                  ->orWhere('buyer_wallet', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $trades = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $trades,
            'message' => __('p2p.trades_loaded'),
        ]);
    }

    /**
     * Show trade detail with buyer proof files.
     */
    public function show(Request $request, string $tradeHash): JsonResponse
    {
        $merchant = $request->merchant;

        $trade = Trade::where('trade_hash', $tradeHash)
            ->where('merchant_id', $merchant->id)
            ->with(['merchant:id,username,wallet_address', 'tradingLink', 'dispute', 'review'])
            ->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => [
                'trade' => $trade,
                'has_bank_proof' => ! empty($trade->bank_proof_path),
                'has_buyer_id' => ! empty($trade->buyer_id_path),
            ],
            'message' => __('p2p.trade_status_loaded'),
        ]);
    }

    /**
     * Download buyer's bank proof file.
     */
    public function downloadBankProof(Request $request, string $tradeHash): StreamedResponse|JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)
            ->where('merchant_id', $request->merchant->id)
            ->first();

        if (! $trade || empty($trade->bank_proof_path)) {
            return response()->json(['message' => __('p2p.not_found')], 404);
        }

        abort_unless(Storage::disk('local')->exists($trade->bank_proof_path), 404);

        return Storage::disk('local')->download(
            $trade->bank_proof_path,
            'bank-proof-' . substr($trade->trade_hash, 0, 10) . '.' . pathinfo($trade->bank_proof_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * Download buyer's ID document.
     */
    public function downloadBuyerId(Request $request, string $tradeHash): StreamedResponse|JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)
            ->where('merchant_id', $request->merchant->id)
            ->first();

        if (! $trade || empty($trade->buyer_id_path)) {
            return response()->json(['message' => __('p2p.not_found')], 404);
        }

        abort_unless(Storage::disk('local')->exists($trade->buyer_id_path), 404);

        return Storage::disk('local')->download(
            $trade->buyer_id_path,
            'buyer-id-' . substr($trade->trade_hash, 0, 10) . '.' . pathinfo($trade->buyer_id_path, PATHINFO_EXTENSION)
        );
    }
}
