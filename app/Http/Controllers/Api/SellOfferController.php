<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellOffer;
use App\Services\SellOfferService;
use App\Settings\TradeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SellOfferController extends Controller
{
    public function __construct(
        private readonly SellOfferService $offers,
        private readonly TradeSettings $tradeSettings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);
        $currency = strtoupper((string) $request->query('currency', ''));
        $payment = strtolower((string) $request->query('payment', ''));
        $seller = strtolower((string) $request->query('seller', ''));

        $query = SellOffer::query()
            ->active()
            ->public()
            ->with(['sellerMerchant:id,username,kyc_status,is_fast_responder,has_liquidity,completion_rate,total_trades,rank_id', 'sellerMerchant.rank:id,name'])
            ->orderByDesc('created_at');

        if ($currency !== '') {
            $query->forCurrency($currency);
        }

        if ($payment !== '') {
            $query->where('payment_methods', 'like', '%' . addslashes($payment) . '%');
        }

        if ($seller !== '' && preg_match('/^0x[a-f0-9]{40}$/', $seller)) {
            $query->where('seller_wallet', $seller);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => [
                'offers' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'message' => __('p2p.sell_offers_loaded'),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $offer = SellOffer::query()->where('slug', $slug)->first();

        if (! $offer) {
            return response()->json(['message' => __('p2p.sell_offer_not_found')], 404);
        }

        return response()->json([
            'data' => [
                'slug' => $offer->slug,
                'trade_id' => $offer->trade_id,
                'seller_wallet' => $offer->seller_wallet,
                'amount_usdc' => $offer->amount_usdc,
                'amount_remaining_usdc' => $offer->amount_remaining_usdc,
                'min_trade_usdc' => $offer->min_trade_usdc,
                'max_trade_usdc' => $offer->max_trade_usdc,
                'currency_code' => $offer->currency_code,
                'fiat_rate' => $offer->fiat_rate,
                'payment_methods' => $offer->payment_methods,
                'instructions' => $offer->instructions,
                'is_active' => $offer->is_active,
                'is_private' => (bool) $offer->is_private,
                'require_kyc' => (bool) $offer->require_kyc,
                'expires_at' => $offer->expires_at?->toIso8601String(),
            ],
            'message' => __('p2p.sell_offer_loaded'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tradeSettings = app(\App\Settings\TradeSettings::class);
        $validated = $request->validate([
            'amount_usdc' => [
                'required', 'numeric',
                'min:' . max(1, (float) $tradeSettings->global_min_trade),
                'max:' . max(1, (float) $tradeSettings->global_max_trade),
            ],
            'currency_code' => ['required', 'string', 'size:3'],
            'fiat_rate' => ['required', 'numeric', 'min:0.000001'],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*.merchant_payment_method_id' => ['required', 'integer', 'exists:merchant_payment_methods,id'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'require_kyc' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
            'trade_id' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/', 'unique:sell_offers,trade_id'],
            'fund_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        try {
            $offer = $this->offers->createOffer($request->merchant, $validated);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $offer,
            'message' => __('p2p.sell_offer_created'),
        ], 201);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $offer = SellOffer::query()->where('slug', $slug)->first();
        if (! $offer) {
            return response()->json(['message' => __('p2p.sell_offer_not_found')], 404);
        }

        $cancelTx = $request->validate([
            'cancel_tx_hash' => ['nullable', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ])['cancel_tx_hash'] ?? null;

        try {
            $offer = $this->offers->cancelOffer($offer, $request->merchant, $cancelTx);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $offer,
            'message' => __('p2p.sell_offer_cancelled'),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $offers = SellOffer::query()
            ->forSellerWallet($request->merchant->wallet_address)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $offers,
            'message' => __('p2p.sell_offers_loaded'),
        ]);
    }
}
