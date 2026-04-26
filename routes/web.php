<?php

use App\Http\Controllers\Admin\KycDownloadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public — Landing (variant chosen by admin setting)
Route::get('/', function () {
    $variant = rescue(
        fn () => app(\App\Settings\GeneralSettings::class)->homepage_variant,
        'classic'
    );
    $component = $variant === 'dynamic' ? 'Landing/Dynamic' : 'Landing/Classic';
    return Inertia::render($component);
})->name('home');

// Public — Connect Wallet
Route::get('/connect', function () {
    return Inertia::render('Auth/Connect');
})->name('connect');

// Public — CMS page (Terms, Privacy, and any admin-created page)
Route::get('/p/{slug}', function (string $slug) {
    $page = \App\Models\Page::query()
        ->published()
        ->where('slug', $slug)
        ->first();

    abort_if(! $page, 404);

    $body = str_replace('{date}', $page->published_at?->format('F j, Y') ?? $page->updated_at->format('F j, Y'), (string) $page->body);
    $html = \Illuminate\Support\Str::markdown($body);

    return Inertia::render('Pages/Show', [
        'page' => [
            'title' => $page->title,
            'slug' => $page->slug,
            'excerpt' => $page->excerpt,
            'cover_image' => $page->cover_image ? asset('storage/' . $page->cover_image) : null,
            'meta_title' => $page->meta_title ?: $page->title,
            'meta_description' => $page->meta_description ?: $page->excerpt,
            'html' => $html,
            'published_at' => $page->published_at?->toIso8601String(),
            'updated_at' => $page->updated_at->toIso8601String(),
        ],
    ]);
})->name('page.show');

Route::get('/setup', function () {
    return Inertia::render('Auth/Setup');
})->name('auth.setup');

// Public — Merchant profile
Route::get('/merchant/{username}', function (string $username) {
    return Inertia::render('Merchant/Profile', ['username' => $username]);
})->name('merchant.profile');

// Public — Sell marketplace + offer details
Route::get('/sell', function () {
    return Inertia::render('Sell/Marketplace');
})->name('sell.marketplace');

Route::get('/sell/o/{slug}', function (string $slug) {
    return Inertia::render('Sell/OfferDetails', ['slug' => $slug]);
})->name('sell.offer');

Route::get('/sell/create', function () {
    return Inertia::render('Sell/Create');
})->name('sell.create');

Route::get('/sell/dashboard', function () {
    return Inertia::render('Sell/Dashboard');
})->name('sell.dashboard');

Route::get('/sell/trade/{tradeHash}', function (string $tradeHash) {
    return Inertia::render('Sell/TradeRoom', ['tradeHash' => $tradeHash]);
})->name('sell.trade');

Route::get('/sell/trade/{tradeHash}/release', function (string $tradeHash) {
    return Inertia::render('Sell/Release', ['tradeHash' => $tradeHash]);
})->name('sell.release');

// Short URL — primary trading link (redirects to merchant profile)
Route::get('/m/{slug}', function (string $slug) {
    $link = \App\Models\MerchantTradingLink::where('slug', $slug)
        ->where('is_active', true)
        ->first();
    if (! $link || ! $link->merchant?->is_active) {
        abort(404);
    }
    return redirect()->route('merchant.profile', $link->merchant->username);
})->name('merchant.short');

// Public — Trade flow
Route::get('/trade/{slug}/start', function (string $slug) {
    return Inertia::render('Trade/Start', ['slug' => $slug]);
})->name('trade.start');

Route::redirect('/trade/{slug}', '/trade/{slug}/start');

Route::get('/verify/{tradeHash}', function (string $tradeHash) {
    return Inertia::render('Trade/Verify', ['tradeHash' => $tradeHash]);
})->name('trade.verify');

Route::get('/trade/{hash}/confirm', function (string $hash) {
    return Inertia::render('Trade/Confirm', ['tradeHash' => $hash]);
})->name('trade.confirm');

Route::get('/trade/{hash}/release', function (string $hash) {
    return Inertia::render('Trade/Release', ['tradeHash' => $hash]);
})->name('trade.release');

Route::get('/trade/{hash}/meeting', function (string $hash) {
    return Inertia::render('Trade/Meeting', ['tradeHash' => $hash]);
})->name('trade.meeting');

// Dashboard — authenticated merchant pages
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/liquidity', function () {
    return Inertia::render('Liquidity');
})->name('liquidity');

Route::get('/trades', function () {
    return Inertia::render('Trades');
})->name('trades');

Route::get('/payments', function () {
    return Inertia::render('PaymentMethods');
})->name('payments');

Route::get('/links', function () {
    return Inertia::render('TradingLinks');
})->name('links');

Route::get('/currency', function () {
    return Inertia::render('CurrencyMarkup');
})->name('currency');

Route::get('/instructions', function () {
    return Inertia::render('Instructions');
})->name('instructions');

Route::get('/verification', function () {
    return Inertia::render('BuyerVerification');
})->name('verification');

Route::get('/security', function () {
    return Inertia::render('Security');
})->name('security');


Route::get('/settings', function () {
    return Inertia::render('Settings');
})->name('settings');

Route::get('/kyc', function () {
    return Inertia::render('Kyc');
})->name('kyc');

Route::get('/reviews', function () {
    return Inertia::render('Reviews');
})->name('reviews');

// Admin downloads
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('kyc/{kycDocument}/download', KycDownloadController::class)
        ->name('admin.kyc.download');
    Route::get('trade/{trade}/bank-proof', [\App\Http\Controllers\Admin\TradeProofDownloadController::class, 'bankProof'])
        ->name('admin.trade.download-bank-proof');
    Route::get('trade/{trade}/buyer-id', [\App\Http\Controllers\Admin\TradeProofDownloadController::class, 'buyerId'])
        ->name('admin.trade.download-buyer-id');
    Route::get('dispute/evidence', function (\Illuminate\Http\Request $request) {
        abort_unless(in_array(auth()->user()?->role, ['super_admin', 'dispute_manager'], true), 403);
        $path = $request->query('path');
        abort_unless($path && str_starts_with($path, 'disputes/') && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);
        // Serve inline (view in browser) instead of forcing download
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mime]);
    })->name('admin.dispute.download-evidence');

    Route::get('email-preview', function () {
        $settings = app(\App\Settings\EmailTemplateSettings::class);
        return view('emails.layout', [
            'subject' => 'Trade #abc123 Initiated',
            'body' => "A new trade has been initiated.\n\nAmount: 100 USDC\nCurrency: DOP\nBuyer: 0xA689...Ae52\n\nPlease check your dashboard for details.",
            'logo' => $settings->logo_path ? asset('storage/' . $settings->logo_path) : '',
            'headerImage' => $settings->header_image_path ? asset('storage/' . $settings->header_image_path) : '',
            'primaryColor' => $settings->primary_color ?: '#8288bf',
            'footer' => $settings->footer_text ?: '',
            'actionUrl' => url('/dashboard'),
            'actionText' => 'View Trade',
        ]);
    })->name('admin.email-preview');
});
