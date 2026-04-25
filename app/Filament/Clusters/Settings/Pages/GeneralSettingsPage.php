<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class GeneralSettingsPage extends SettingsPage
{
    protected static ?string $cluster = Settings::class;

    protected static string $settings = GeneralSettings::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('settings.general');
    }

    public function getTitle(): string
    {
        return __('settings.general');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_array($data['logo_path'] ?? null)) {
            $data['logo_path'] = collect($data['logo_path'])->first();
        }
        if (is_array($data['favicon_path'] ?? null)) {
            $data['favicon_path'] = collect($data['favicon_path'])->first();
        }

        return $data;
    }

    public function afterSave(): void
    {
        Cache::forget('site_settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.branding.title'))
                    ->schema([
                        TextInput::make('site_name')
                            ->label(__('settings.branding.site_name'))
                            ->required(),
                        TextInput::make('site_description')
                            ->label(__('settings.branding.site_description')),
                        TextInput::make('support_email')
                            ->label(__('settings.branding.support_email'))
                            ->email()
                            ->required(),
                        TextInput::make('support_url')
                            ->label(__('settings.branding.support_url'))
                            ->helperText(__('settings.branding.support_url_help'))
                            ->url()
                            ->maxLength(255),
                        FileUpload::make('logo_path')
                            ->label(__('settings.branding.logo'))
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public'),
                        FileUpload::make('favicon_path')
                            ->label(__('settings.branding.favicon'))
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public'),
                    ])
                    ->columns(2),

                Section::make(__('settings.regional.title'))
                    ->schema([
                        TextInput::make('default_currency')
                            ->label(__('settings.regional.default_currency'))
                            ->required()
                            ->maxLength(5),
                        TextInput::make('default_country')
                            ->label(__('settings.regional.default_country'))
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(__('settings.features.title'))
                    ->schema([
                        Toggle::make('merchant_registration_enabled')
                            ->label(__('settings.features.merchant_registration_enabled')),
                        Toggle::make('p2p_trading_enabled')
                            ->label(__('settings.features.p2p_trading_enabled')),
                        Toggle::make('cash_meetings_enabled')
                            ->label(__('settings.features.cash_meetings_enabled')),
                    ]),

                Section::make(__('settings.homepage.title'))
                    ->schema([
                        Select::make('homepage_variant')
                            ->label(__('settings.homepage.variant'))
                            ->helperText(__('settings.homepage.variant_help'))
                            ->options([
                                'classic' => __('settings.homepage.classic'),
                                'dynamic' => __('settings.homepage.dynamic'),
                            ])
                            ->required()
                            ->native(false),
                    ]),

                Section::make(__('settings.translation.title'))
                    ->schema([
                        Toggle::make('weglot_enabled')
                            ->label(__('settings.translation.enabled')),
                        TextInput::make('weglot_api_key')
                            ->label(__('settings.translation.api_key'))
                            ->helperText(__('settings.translation.api_key_help'))
                            ->maxLength(255),
                    ])
                    ->columns(1),
            ]);
    }
}
