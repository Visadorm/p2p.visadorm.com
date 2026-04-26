<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings;
use App\Settings\TradeSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class TradeSettingsPage extends SettingsPage
{
    protected static ?string $cluster = Settings::class;

    protected static string $settings = TradeSettings::class;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('settings.trade');
    }

    public function getTitle(): string
    {
        return __('settings.trade');
    }

    public function afterSave(): void
    {
        Cache::forget('feature_flags');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.trade_defaults.title'))
                    ->schema([
                        TextInput::make('default_trade_timer_minutes')
                            ->label(__('settings.trade_defaults.default_trade_timer_minutes'))
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('max_trade_timer_minutes')
                            ->label(__('settings.trade_defaults.max_trade_timer_minutes'))
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('stake_amount')
                            ->label(__('settings.trade_defaults.stake_amount'))
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('global_min_trade')
                            ->label(__('settings.trade_defaults.global_min_trade'))
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('global_max_trade')
                            ->label(__('settings.trade_defaults.global_max_trade'))
                            ->required()
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(2),

                Section::make(__('settings.escrow.title'))
                    ->schema([
                        TextInput::make('liquidity_badge_threshold')
                            ->label(__('settings.escrow.liquidity_badge_threshold'))
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('fast_responder_minutes')
                            ->label(__('settings.escrow.fast_responder_minutes'))
                            ->required()
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->columns(2),

                Section::make(__('settings.cleanup.title'))
                    ->schema([
                        TextInput::make('trade_expiry_cleanup_minutes')
                            ->label(__('settings.cleanup.trade_expiry_cleanup_minutes'))
                            ->required()
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->columns(2),

                Section::make(__('settings.buyer_verification.title'))
                    ->schema([
                        Select::make('default_buyer_verification')
                            ->label(__('settings.buyer_verification.default_buyer_verification'))
                            ->options([
                                'none' => __('settings.buyer_verification.none'),
                                'optional' => __('settings.buyer_verification.optional'),
                                'required' => __('settings.buyer_verification.required'),
                            ])
                            ->required(),
                    ]),

                Section::make(__('settings.sell.title'))
                    ->schema([
                        Toggle::make('sell_enabled')
                            ->label(__('settings.sell.enabled'))
                            ->helperText(__('settings.sell.enabled_help')),
                        \Filament\Forms\Components\Placeholder::make('cash_sell_status')
                            ->label(__('settings.sell.cash_meeting_enabled'))
                            ->content(__('settings.sell.cash_sell_coming_soon')),
                        TextInput::make('sell_max_offers_per_wallet')
                            ->label(__('settings.sell.max_offers_per_wallet'))
                            ->numeric()->minValue(1)->required(),
                        TextInput::make('sell_max_outstanding_usdc')
                            ->label(__('settings.sell.max_outstanding_usdc'))
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('sell_kyc_threshold_usdc')
                            ->label(__('settings.sell.kyc_threshold_usdc'))
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('sell_kyc_threshold_window_days')
                            ->label(__('settings.sell.kyc_threshold_window_days'))
                            ->numeric()->minValue(1)->required(),
                        TextInput::make('sell_default_offer_timer_minutes')
                            ->label(__('settings.sell.default_offer_timer_minutes'))
                            ->numeric()->minValue(1)->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
