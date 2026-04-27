<?php

namespace App\Filament\Resources\TradeResource\Tables;

use App\Enums\PaymentMethodType;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trade_hash')
                    ->label(__('trade.trade_hash'))
                    ->limit(10)
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('merchant.username')
                    ->label(__('trade.merchant'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('seller_wallet')
                    ->label(__('trade.seller_wallet'))
                    ->limit(10)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('buyer_wallet')
                    ->label(__('trade.buyer_wallet'))
                    ->limit(10)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('is_cash_trade')
                    ->label('Cash')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? 'Cash' : '—')
                    ->toggleable(),

                TextColumn::make('amount_usdc')
                    ->label(__('trade.amount_usdc'))
                    ->money('usd')
                    ->sortable(),

                TextColumn::make('currency_code')
                    ->label(__('trade.currency_code'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('p2p.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label(__('trade.payment_method'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label(__('trade.type_label'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('p2p.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('p2p.status'))
                    ->options(TradeStatus::class)
                    ->multiple()
                    ->searchable(),

                SelectFilter::make('type')
                    ->label(__('trade.type_label'))
                    ->options(TradeType::class),

                SelectFilter::make('payment_method')
                    ->label(__('trade.payment_method'))
                    ->options(PaymentMethodType::class),

                \Filament\Tables\Filters\TernaryFilter::make('is_cash_trade')
                    ->label('Cash trade'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
