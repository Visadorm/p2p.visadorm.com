<?php

namespace App\Http\Middleware;

use App\Enums\TradeStatus;
use App\Models\Merchant;
use App\Models\Trade;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'platform_stats' => fn () => Cache::remember('platform_stats', 3600, fn () => rescue(fn () => [
                'total_trades' => Trade::where('status', TradeStatus::Completed)->count(),
                'total_volume' => round((float) Trade::where('status', TradeStatus::Completed)->sum('amount_usdc')),
                'total_merchants' => Merchant::where('is_active', true)->count(),
                'avg_completion' => round((float) Merchant::where('total_trades', '>', 0)->avg('completion_rate'), 1),
            ], ['total_trades' => 0, 'total_volume' => 0, 'total_merchants' => 0, 'avg_completion' => 0])),
            'site' => fn () => Cache::remember('site_settings', 3600, function () {
                if (! \Illuminate\Support\Facades\Schema::hasTable('settings')) {
                    return [
                        'name' => 'Visadorm P2P',
                        'description' => '',
                        'logo' => null,
                        'favicon' => null,
                        'support_email' => '',
                        'homepage_variant' => 'classic',
                        'weglot' => ['enabled' => false, 'api_key' => null],
                    ];
                }
                $s = app(GeneralSettings::class);
                return [
                    'name' => $s->site_name,
                    'description' => $s->site_description,
                    'logo' => $s->logo_path ? asset('storage/' . $s->logo_path) : null,
                    'favicon' => $s->favicon_path ? asset('storage/' . $s->favicon_path) : null,
                    'support_email' => $s->support_email,
                    'support_url' => $s->support_url ?: ($s->support_email ? 'mailto:' . $s->support_email : null),
                    'homepage_variant' => $s->homepage_variant,
                    'weglot' => [
                        'enabled' => $s->weglot_enabled,
                        'api_key' => $s->weglot_enabled ? $s->weglot_api_key : null,
                    ],
                ];
            }),
            'pages' => fn () => Cache::remember('public_pages_nav', 3600, function () {
                if (! \Illuminate\Support\Facades\Schema::hasTable('pages')) {
                    return ['header' => [], 'footer' => []];
                }
                $all = \App\Models\Page::query()
                    ->published()
                    ->ordered()
                    ->get(['title', 'slug', 'show_in_header', 'show_in_footer']);
                return [
                    'header' => $all->where('show_in_header', true)->map(fn ($p) => ['title' => $p->title, 'slug' => $p->slug])->values()->all(),
                    'footer' => $all->where('show_in_footer', true)->map(fn ($p) => ['title' => $p->title, 'slug' => $p->slug])->values()->all(),
                ];
            }),
            'blockchain' => fn () => Cache::remember('blockchain_config', 3600, fn () => rescue(fn () => [
                'usdc_address'         => app(\App\Settings\BlockchainSettings::class)->usdc_address,
                'trade_escrow_address' => app(\App\Settings\BlockchainSettings::class)->trade_escrow_address,
                'soulbound_nft_address' => app(\App\Settings\BlockchainSettings::class)->soulbound_nft_address,
                'chain_id'             => app(\App\Settings\BlockchainSettings::class)->chain_id,
                'rpc_url'              => app(\App\Settings\BlockchainSettings::class)->rpc_url,
                'network'              => app(\App\Settings\BlockchainSettings::class)->network,
            ], [])),
            'features' => fn () => Cache::remember('feature_flags', 3600, fn () => rescue(fn () => [
                'sell_enabled'              => app(\App\Settings\TradeSettings::class)->sell_enabled,
                'sell_cash_trade_enabled'   => app(\App\Settings\TradeSettings::class)->sell_cash_trade_enabled,
                'sell_default_expiry_minutes' => app(\App\Settings\TradeSettings::class)->sell_default_expiry_minutes,
                'sell_anti_spam_stake_usdc' => app(\App\Settings\TradeSettings::class)->sell_anti_spam_stake_usdc,
                'sell_require_stake_public' => app(\App\Settings\TradeSettings::class)->sell_require_stake_public,
                'sell_require_stake_link'   => app(\App\Settings\TradeSettings::class)->sell_require_stake_link,
                'sell_require_stake_cash'   => app(\App\Settings\TradeSettings::class)->sell_require_stake_cash,
            ], [
                'sell_enabled' => true,
                'sell_cash_trade_enabled' => true,
                'sell_default_expiry_minutes' => 60,
                'sell_anti_spam_stake_usdc' => 5,
                'sell_require_stake_public' => true,
                'sell_require_stake_link' => false,
                'sell_require_stake_cash' => true,
            ])),
        ];
    }
}
