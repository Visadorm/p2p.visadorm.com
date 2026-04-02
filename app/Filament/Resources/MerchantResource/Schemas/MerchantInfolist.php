<?php

namespace App\Filament\Resources\MerchantResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MerchantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('merchant.section_profile'))
                    ->schema([
                        TextEntry::make('username')
                            ->label(__('merchant.username'))
                            ->weight('bold'),

                        TextEntry::make('wallet_address')
                            ->label(__('p2p.wallet_address'))
                            ->copyable(),

                        TextEntry::make('email')
                            ->label(__('merchant.email'))
                            ->placeholder('—'),

                        TextEntry::make('bio')
                            ->label(__('merchant.bio'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('rank.name')
                            ->label(__('merchant.rank')),

                        IconEntry::make('is_legendary')
                            ->label(__('merchant.is_legendary'))
                            ->boolean(),

                        TextEntry::make('kyc_status')
                            ->label(__('kyc.status_label'))
                            ->badge(),

                        TextEntry::make('buyer_verification')
                            ->label(__('merchant.buyer_verification_label'))
                            ->badge(),

                        TextEntry::make('member_since')
                            ->label(__('merchant.member_since'))
                            ->date(),
                    ])
                    ->columns(2),

                Section::make(__('merchant.section_stats'))
                    ->schema([
                        TextEntry::make('total_trades')
                            ->label(__('merchant.total_trades')),

                        TextEntry::make('total_volume')
                            ->label(__('merchant.total_volume'))
                            ->money('USD'),

                        TextEntry::make('completion_rate')
                            ->label(__('merchant.completion_rate'))
                            ->suffix('%'),

                        TextEntry::make('reliability_score')
                            ->label(__('merchant.reliability_score'))
                            ->suffix(' / 10'),

                        TextEntry::make('dispute_rate')
                            ->label(__('merchant.dispute_rate'))
                            ->suffix('%'),

                        TextEntry::make('avg_response_minutes')
                            ->label(__('merchant.avg_response'))
                            ->suffix(' min'),
                    ])
                    ->columns(2),

                Section::make(__('merchant.section_badges'))
                    ->schema([
                        IconEntry::make('bank_verified')
                            ->label(__('merchant.bank_verified'))
                            ->boolean(),

                        IconEntry::make('email_verified')
                            ->label(__('merchant.email_verified'))
                            ->boolean(),

                        IconEntry::make('business_verified')
                            ->label(__('merchant.business_verified'))
                            ->boolean(),

                        IconEntry::make('is_fast_responder')
                            ->label(__('merchant.is_fast_responder'))
                            ->boolean(),

                        IconEntry::make('has_liquidity')
                            ->label(__('merchant.has_liquidity'))
                            ->boolean(),

                        IconEntry::make('is_active')
                            ->label(__('merchant.is_active'))
                            ->boolean(),

                        IconEntry::make('is_online')
                            ->label(__('merchant.is_online'))
                            ->boolean(),

                        TextEntry::make('last_seen_at')
                            ->label(__('merchant.last_seen'))
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make(__('merchant.section_settings'))
                    ->schema([
                        TextEntry::make('trade_instructions')
                            ->label(__('merchant.trade_instructions'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('trade_timer_minutes')
                            ->label(__('merchant.trade_timer'))
                            ->suffix(' min'),
                    ])
                    ->columns(2),
            ]);
    }
}
