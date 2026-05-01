<?php

namespace App\Filament\Resources\MerchantResource\Tables;

use App\Enums\KycStatus;
use App\Models\Merchant;
use App\Services\KycService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
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

                TextColumn::make('kyc_locked_at')
                    ->label('Identity Profile')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Submitted' : 'Not submitted')
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->tooltip(fn ($record) => $record->kyc_locked_at
                        ? 'Submitted '.$record->kyc_locked_at->diffForHumans()
                        : null)
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

                TernaryFilter::make('kyc_profile_submitted')
                    ->label(__('merchant.kyc_profile_pending_filter'))
                    ->placeholder('Any')
                    ->trueLabel('Yes (locked profile)')
                    ->falseLabel('No (not submitted)')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('kyc_locked_at'),
                        false: fn ($q) => $q->whereNull('kyc_locked_at'),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // A8: admin override — clears KYC lock so the merchant
                    // can amend their submission. Audit logged via KycService.
                    Action::make('unlock_kyc')
                        ->label(__('merchant.unlock_kyc'))
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Merchant $record): bool => $record->kyc_locked_at !== null)
                        ->action(function (Merchant $record): void {
                            app(KycService::class)->adminUnlockProfile($record, auth()->id() ?? 0);
                            Notification::make()
                                ->title(__('merchant.kyc_unlocked_title'))
                                ->body(__('merchant.kyc_unlocked_body'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
