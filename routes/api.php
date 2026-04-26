<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EscrowController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\MerchantCurrencyController;
use App\Http\Controllers\Api\MerchantPaymentMethodController;
use App\Http\Controllers\Api\MerchantTradeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SellOfferController;
use App\Http\Controllers\Api\SellTradeController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\TradingLinkController;
use App\Http\Middleware\EnsureWalletAuthenticated;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('nonce', [AuthController::class, 'nonce'])->name('api.auth.nonce');
    Route::post('verify', [AuthController::class, 'verify'])->name('api.auth.verify');
});

// Public merchant profile
Route::get('merchant/{username}/profile', [MerchantController::class, 'profile'])
    ->name('api.merchant.profile');

// Public merchant listing (for landing page carousel + buy/sell listing)
Route::get('merchants', [MerchantController::class, 'listing'])
    ->middleware('throttle:60,1')
    ->name('api.merchants.listing');

// Public exchange rates
Route::get('exchange-rates', [ExchangeRateController::class, 'index'])
    ->name('api.exchange-rates');

// Public country list (cached 24h)
Route::get('countries', function () {
    $countries = \Illuminate\Support\Facades\Cache::remember('countries_list', 86400, function () {
        return \Nnjeim\World\Models\Country::query()
            ->select(['iso2', 'name', 'phone_code'])
            ->orderBy('name')
            ->get()
            ->toArray();
    });
    return response()->json([
        'data' => $countries,
        'message' => __('p2p.countries_loaded'),
    ]);
})->middleware('throttle:60,1')->name('api.countries');

// Public trading link details
Route::get('trade/{slug}', [TradeController::class, 'show'])
    ->name('api.trade.show');

// Public trade verification (no auth — for QR code scanning)
Route::get('trade/{tradeHash}/verify', [TradeController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('api.trade.verify');

// Public sell offers
Route::get('sell-offers', [SellOfferController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.sell-offers.index');
Route::get('sell-offer/{slug}', [SellOfferController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.sell-offer.show');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Sanctum + Wallet Middleware)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', EnsureWalletAuthenticated::class])->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('api.auth.me');

    // Merchant Dashboard & Profile
    Route::get('merchant/dashboard', [MerchantController::class, 'show'])->name('api.merchant.dashboard');
    Route::put('merchant/profile', [MerchantController::class, 'update'])->name('api.merchant.update');
    Route::post('merchant/avatar', [MerchantController::class, 'uploadAvatar'])->name('api.merchant.avatar');

    // Trading Links
    Route::prefix('merchant/trading-links')->name('api.trading-links.')->group(function () {
        Route::get('/', [TradingLinkController::class, 'index'])->name('index');
        Route::post('/', [TradingLinkController::class, 'store'])->name('store');
        Route::put('{link}', [TradingLinkController::class, 'update'])->name('update');
        Route::delete('{link}', [TradingLinkController::class, 'destroy'])->name('destroy');
    });

    // Payment Methods
    Route::prefix('merchant/payment-methods')->name('api.payment-methods.')->group(function () {
        Route::get('/', [MerchantPaymentMethodController::class, 'index'])->name('index');
        Route::post('/', [MerchantPaymentMethodController::class, 'store'])->name('store');
        Route::put('{paymentMethod}', [MerchantPaymentMethodController::class, 'update'])->name('update');
        Route::delete('{paymentMethod}', [MerchantPaymentMethodController::class, 'destroy'])->name('destroy');
    });

    // Currencies
    Route::prefix('merchant/currencies')->name('api.currencies.')->group(function () {
        Route::get('/', [MerchantCurrencyController::class, 'index'])->name('index');
        Route::post('/', [MerchantCurrencyController::class, 'store'])->name('store');
        Route::put('{currency}', [MerchantCurrencyController::class, 'update'])->name('update');
        Route::delete('{currency}', [MerchantCurrencyController::class, 'destroy'])->name('destroy');
    });

    // Trade Actions (buyer) — rate-limited to prevent spam
    Route::middleware(['throttle:10,5', \App\Http\Middleware\EnsureP2pTradingEnabled::class])->group(function () {
        Route::post('trade/{slug}/initiate', [TradeController::class, 'initiate'])->name('api.trade.initiate');
        Route::post('trade/{tradeHash}/paid', [TradeController::class, 'markPaid'])->name('api.trade.paid');
        Route::post('trade/{tradeHash}/cancel', [TradeController::class, 'cancel'])->name('api.trade.cancel');
    });
    Route::get('trade/{tradeHash}/status', [TradeController::class, 'status'])->name('api.trade.status');
    Route::post('trade/{tradeHash}/bank-proof', [TradeController::class, 'uploadBankProof'])->name('api.trade.bank-proof');
    Route::post('trade/{tradeHash}/buyer-id', [TradeController::class, 'uploadBuyerId'])->name('api.trade.buyer-id');

    // Trade Actions (merchant)
    Route::get('merchant/trades', [MerchantTradeController::class, 'index'])->name('api.merchant.trades');
    Route::get('merchant/trades/{tradeHash}', [MerchantTradeController::class, 'show'])->name('api.merchant.trades.show');
    Route::get('merchant/trades/{tradeHash}/bank-proof', [MerchantTradeController::class, 'downloadBankProof'])->name('api.merchant.trades.bank-proof');
    Route::get('merchant/trades/{tradeHash}/buyer-id', [MerchantTradeController::class, 'downloadBuyerId'])->name('api.merchant.trades.buyer-id');
    Route::post('merchant/trades/{tradeHash}/confirm', [TradeController::class, 'confirm'])->name('api.trade.confirm');

    // Reviews
    Route::post('trade/{tradeHash}/review', [ReviewController::class, 'store'])->name('api.trade.review');

    // KYC
    Route::prefix('merchant/kyc')->name('api.kyc.')->group(function () {
        Route::get('/', [KycController::class, 'index'])->name('index');
        Route::post('upload', [KycController::class, 'upload'])->name('upload');
        Route::delete('{document}', [KycController::class, 'destroy'])->name('destroy');
    });

    // Escrow — rate-limited to prevent abuse
    Route::prefix('merchant/escrow')->name('api.escrow.')->group(function () {
        Route::post('deposit', [EscrowController::class, 'deposit'])->middleware('throttle:5,5')->name('deposit');
        Route::post('withdraw', [EscrowController::class, 'withdraw'])->middleware('throttle:5,5')->name('withdraw');
        Route::get('tx/{hash}', [EscrowController::class, 'txStatus'])->name('tx-status');
    });

    // Disputes — rate-limited, keyed by unguessable trade_hash (not enumerable integer id)
    Route::post('trade/{tradeHash}/dispute', [DisputeController::class, 'store'])->middleware('throttle:5,5')->name('api.dispute.store');
    Route::get('trade/{tradeHash}/dispute', [DisputeController::class, 'show'])->name('api.dispute.show');
    Route::post('trade/{tradeHash}/dispute/evidence', [DisputeController::class, 'uploadEvidence'])->middleware('throttle:10,5')->name('api.dispute.evidence');

    // Admin: resolve dispute
    Route::post('admin/trade/{tradeHash}/dispute/resolve', [DisputeController::class, 'resolve'])
        ->middleware('throttle:10,1')
        ->name('api.dispute.resolve');

    // Sell offers — seller-side management
    Route::prefix('sell-offers')->name('api.sell-offers.')->group(function () {
        Route::get('mine', [SellOfferController::class, 'mine'])->name('mine');
        Route::post('/', [SellOfferController::class, 'store'])->middleware(['throttle:10,5', \App\Http\Middleware\EnsureP2pTradingEnabled::class])->name('store');
        Route::delete('{slug}', [SellOfferController::class, 'destroy'])->middleware('throttle:10,5')->name('destroy');
    });

    // Sell trade lifecycle (seller-direct via meta-tx + buyer-direct on-chain)
    Route::middleware(['throttle:10,5', \App\Http\Middleware\EnsureP2pTradingEnabled::class])->group(function () {
        Route::post('sell-offer/{slug}/take', [SellTradeController::class, 'take'])->name('api.sell-trade.take');
        Route::post('trade/{tradeHash}/sell/mark-paid', [SellTradeController::class, 'markPaid'])->name('api.sell-trade.mark-paid');
        Route::post('trade/{tradeHash}/sell/release', [SellTradeController::class, 'release'])->name('api.sell-trade.release');
        Route::post('trade/{tradeHash}/sell/dispute', [SellTradeController::class, 'dispute'])->name('api.sell-trade.dispute');
    });
    Route::get('trade/{tradeHash}/sell/release-payload', [SellTradeController::class, 'releasePayload'])
        ->name('api.sell-trade.release-payload');

    // Notifications
    Route::prefix('notifications')->name('api.notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    });
});
