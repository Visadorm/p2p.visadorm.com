<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings;
use App\Settings\FeeSettings;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeeSettingsPage extends SettingsPage
{
    protected static ?string $cluster = Settings::class;

    protected static string $settings = FeeSettings::class;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('settings.fees');
    }

    public function getTitle(): string
    {
        return __('settings.fees');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.p2p_fees.title'))
                    ->description(__('settings.p2p_fees.note'))
                    ->schema([
                        TextInput::make('p2p_fee_percent')
                            ->label(__('settings.p2p_fees.p2p_fee_percent'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('%'),
                    ]),

                Section::make(__('settings.lock_period.title'))
                    ->schema([
                        TextInput::make('fund_lock_hours')
                            ->label(__('settings.lock_period.fund_lock_hours'))
                            ->required()
                            ->numeric()
                            ->minValue(0),
                    ]),
            ]);
    }
}
