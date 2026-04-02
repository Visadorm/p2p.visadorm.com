<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings;
use App\Settings\NotificationSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationSettingsPage extends SettingsPage
{
    protected static ?string $cluster = Settings::class;

    protected static string $settings = NotificationSettings::class;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('settings.notifications');
    }

    public function getTitle(): string
    {
        return __('settings.notifications');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.alerts.title'))
                    ->schema([
                        Toggle::make('alert_new_dispute')
                            ->label(__('settings.alerts.alert_new_dispute')),
                        Toggle::make('alert_kyc_pending')
                            ->label(__('settings.alerts.alert_kyc_pending')),
                        Toggle::make('alert_low_gas')
                            ->label(__('settings.alerts.alert_low_gas')),
                        Toggle::make('alert_large_trade')
                            ->label(__('settings.alerts.alert_large_trade')),
                        TextInput::make('large_trade_threshold')
                            ->label(__('settings.alerts.large_trade_threshold'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('USDC'),
                    ]),

                Section::make(__('settings.email.title'))
                    ->schema([
                        TextInput::make('admin_email')
                            ->label(__('settings.email.admin_email'))
                            ->email()
                            ->required(),
                        Toggle::make('email_notifications_enabled')
                            ->label(__('settings.email.email_notifications_enabled')),
                    ]),

                Section::make('SMS (Twilio)')
                    ->schema([
                        Toggle::make('sms_notifications_enabled')
                            ->label('Enable SMS notifications for merchants')
                            ->helperText('When enabled, merchants can opt into SMS alerts. Requires Twilio credentials in .env (TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM).'),
                    ]),
            ]);
    }
}
