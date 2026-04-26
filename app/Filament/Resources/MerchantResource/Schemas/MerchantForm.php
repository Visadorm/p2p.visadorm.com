<?php

namespace App\Filament\Resources\MerchantResource\Schemas;

use App\Enums\BuyerVerification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MerchantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('merchant.section_profile'))
                    ->schema([
                        TextInput::make('username')
                            ->label(__('merchant.username'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('wallet_address')
                            ->label(__('merchant.wallet_address'))
                            ->disabled(),

                        TextInput::make('email')
                            ->label(__('merchant.email'))
                            ->email()
                            ->maxLength(255),

                        TextInput::make('bio')
                            ->label(__('merchant.bio'))
                            ->maxLength(500),

                        TextInput::make('full_name')
                            ->label(__('merchant.full_name'))
                            ->maxLength(100),

                        TextInput::make('business_name')
                            ->label(__('merchant.business_name'))
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Section::make(__('merchant.section_settings'))
                    ->schema([
                        Select::make('rank_id')
                            ->label(__('merchant.rank'))
                            ->relationship('rank', 'name')
                            ->searchable()
                            ->preload(),

                        Toggle::make('is_legendary')
                            ->label(__('merchant.is_legendary')),

                        Select::make('buyer_verification')
                            ->label(__('merchant.buyer_verification_label'))
                            ->options(BuyerVerification::class)
                            ->required(),

                        TextInput::make('trade_timer_minutes')
                            ->label(__('merchant.trade_timer'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn () => max(1, (int) app(\App\Settings\TradeSettings::class)->max_trade_timer_minutes))
                            ->helperText(fn () => 'Max allowed by platform: ' . (int) app(\App\Settings\TradeSettings::class)->max_trade_timer_minutes . ' minutes'),

                        Toggle::make('is_active')
                            ->label(__('merchant.is_active')),
                    ])
                    ->columns(2),
            ]);
    }
}
