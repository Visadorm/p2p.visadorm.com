<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellOfferResource\Tables;

use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SellOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->limit(12)
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('trade_id')
                    ->label('On-chain ID')
                    ->limit(10)
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('fund_tx_hash')
                    ->label('Funded')
                    ->boolean()
                    ->getStateUsing(fn ($record) => ! empty($record->fund_tx_hash))
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('seller_wallet')
                    ->limit(10)
                    ->searchable(),
                TextColumn::make('amount_usdc')
                    ->money('usd')
                    ->sortable(),
                TextColumn::make('amount_remaining_usdc')
                    ->label('Remaining')
                    ->money('usd')
                    ->sortable(),
                TextColumn::make('currency_code')
                    ->sortable(),
                TextColumn::make('fiat_rate')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_private')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('require_kyc')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                TernaryFilter::make('is_private'),
                TernaryFilter::make('require_kyc'),
                SelectFilter::make('currency_code')
                    ->options(fn () => \App\Models\SellOffer::query()
                        ->distinct()
                        ->orderBy('currency_code')
                        ->pluck('currency_code', 'currency_code')
                        ->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }
}
