<?php

namespace App\Filament\Resources\MerchantRankResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MerchantRanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('merchant.rank_fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('slug')
                    ->label(__('merchant.rank_fields.slug'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('min_trades')
                    ->label(__('merchant.rank_fields.min_trades'))
                    ->sortable(),

                TextColumn::make('min_completion_rate')
                    ->label(__('merchant.rank_fields.min_completion_rate'))
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('min_volume')
                    ->label(__('merchant.rank_fields.min_volume'))
                    ->money('usd')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('merchant.rank_fields.sort_order'))
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
