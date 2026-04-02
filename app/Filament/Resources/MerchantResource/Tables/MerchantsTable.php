<?php

namespace App\Filament\Resources\MerchantResource\Tables;

use App\Enums\KycStatus;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MerchantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label(__('merchant.username'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('wallet_address')
                    ->label(__('merchant.wallet_address'))
                    ->limit(10)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('rank.name')
                    ->label(__('merchant.rank'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('kyc_status')
                    ->label(__('merchant.kyc_status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('total_trades')
                    ->label(__('merchant.total_trades'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_volume')
                    ->label(__('merchant.volume'))
                    ->money('usd')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label(__('merchant.is_active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kyc_status')
                    ->label(__('merchant.kyc_status'))
                    ->options(KycStatus::class),

                SelectFilter::make('rank_id')
                    ->label(__('merchant.rank'))
                    ->relationship('rank', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label(__('merchant.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
