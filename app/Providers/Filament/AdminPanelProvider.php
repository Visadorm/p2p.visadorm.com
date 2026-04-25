<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use App\Filament\Clusters\Profile\Pages\ChangePasswordPage;
use App\Filament\Clusters\Profile\Pages\TwoFactorAuthPage;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Schema;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $settings = Schema::hasTable('settings') ? rescue(fn () => app(GeneralSettings::class), null) : null;

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName($settings?->site_name ?: 'Visadorm P2P')
            ->colors([
                'primary' => Color::Amber,
            ]);

        if ($settings?->logo_path) {
            $panel->brandLogo(asset('storage/' . $settings->logo_path))
                  ->brandLogoHeight('2rem');
        }

        if ($settings?->favicon_path) {
            $panel->favicon(asset('storage/' . $settings->favicon_path));
        }

        return $panel
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
            )
            ->userMenuItems([
                'password' => MenuItem::make()
                    ->label(__('settings.password.title'))
                    ->url(fn (): string => ChangePasswordPage::getUrl())
                    ->icon('heroicon-o-key'),
                '2fa' => MenuItem::make()
                    ->label(__('settings.two_factor.title'))
                    ->url(fn (): string => TwoFactorAuthPage::getUrl())
                    ->icon('heroicon-o-shield-check'),
            ])
            ->plugins([
                FilamentApexChartsPlugin::make(),
            ])
            ->navigationGroups([
                __('p2p.nav.dashboard'),
                __('p2p.nav.p2p_trading'),
                __('p2p.nav.verification'),
                __('page.nav.group'),
                __('p2p.nav.settings'),
            ])
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->globalSearch(false)
            ->renderHook('panels::styles.after', fn () => '<link rel="stylesheet" href="/css/filament-custom.css">')
            ->spa()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->databaseNotifications()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
