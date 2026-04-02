<?php

namespace App\Filament\Resources\ReviewResource\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('merchant.username')
                    ->label(__('trade.merchant'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('reviewer_wallet')
                    ->label(__('trade.buyer_wallet'))
                    ->limit(10)
                    ->searchable(),

                TextColumn::make('rating')
                    ->label(__('p2p.rating'))
                    ->sortable(),

                TextColumn::make('comment')
                    ->label(__('p2p.comment'))
                    ->limit(50)
                    ->toggleable(),

                IconColumn::make('is_hidden')
                    ->label('Hidden')
                    ->boolean()
                    ->trueIcon(Heroicon::EyeSlash)
                    ->falseIcon(Heroicon::Eye)
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('created_at')
                    ->label(__('p2p.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_hidden')
                    ->label('Visibility')
                    ->options([
                        '0' => 'Visible',
                        '1' => 'Hidden',
                    ]),
            ])
            ->recordActions([
                Action::make('toggle_hidden')
                    ->label(fn (Review $record): string => $record->is_hidden ? 'Unhide' : 'Hide')
                    ->icon(fn (Review $record): Heroicon => $record->is_hidden ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
                    ->color(fn (Review $record): string => $record->is_hidden ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(function (Review $record): void {
                        $record->update(['is_hidden' => ! $record->is_hidden]);

                        Notification::make()
                            ->title($record->is_hidden ? 'Review hidden' : 'Review visible')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
