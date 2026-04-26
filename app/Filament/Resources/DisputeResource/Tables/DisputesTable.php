<?php

namespace App\Filament\Resources\DisputeResource\Tables;

use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Events\TradeCompleted;
use App\Models\Dispute;
use App\Services\BlockchainService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class DisputesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trade.trade_hash')
                    ->label(__('trade.trade_hash'))
                    ->limit(10)
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('opened_by')
                    ->label(__('trade.opened_by'))
                    ->limit(10)
                    ->searchable(),

                TextColumn::make('trade.amount_usdc')
                    ->label(__('trade.amount_usdc'))
                    ->money('usd')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('p2p.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('p2p.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('p2p.status'))
                    ->options(DisputeStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('resolve_for_buyer')
                        ->label(__('trade.resolve_for_buyer'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('resolution_notes')
                                ->label(__('trade.resolution_notes'))
                                ->required(),
                        ])
                        ->action(function (Dispute $record, array $data): void {
                            $trade = $record->trade;

                            // Blockchain FIRST — if it fails, don't update DB
                            try {
                                if ($trade) {
                                    app(BlockchainService::class)->resolveDispute(
                                        $trade->trade_hash,
                                        $trade->buyer_wallet,
                                    );
                                }
                            } catch (\Throwable $e) {
                                Log::error('Dispute resolve blockchain error (buyer)', [
                                    'dispute_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                                Notification::make()->title('Blockchain error: ' . $e->getMessage())->danger()->send();
                                return;
                            }

                            // DB updates only after blockchain succeeds
                            $record->update([
                                'status' => DisputeStatus::ResolvedBuyer,
                                'resolution_notes' => $data['resolution_notes'],
                                'resolved_by' => auth()->user()?->email . ' (winner: buyer)',
                            ]);

                            $trade?->update([
                                'status' => TradeStatus::Completed,
                                'completed_at' => now(),
                            ]);

                            // Dispatch TradeCompleted so stats/rank update
                            if ($trade) {
                                TradeCompleted::dispatch($trade);
                            }

                            Notification::make()
                                ->title(__('trade.resolve_for_buyer'))
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (Dispute $record): bool => $record->status !== DisputeStatus::Open),

                    Action::make('resolve_for_merchant')
                        ->label(__('trade.resolve_for_merchant'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('resolution_notes')
                                ->label(__('trade.resolution_notes'))
                                ->required(),
                        ])
                        ->action(function (Dispute $record, array $data): void {
                            $trade = $record->trade;

                            // Blockchain FIRST
                            try {
                                if ($trade) {
                                    app(BlockchainService::class)->resolveDispute(
                                        $trade->trade_hash,
                                        $trade->merchant->wallet_address,
                                    );
                                }
                            } catch (\Throwable $e) {
                                Log::error('Dispute resolve blockchain error (merchant)', [
                                    'dispute_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                                Notification::make()->title('Blockchain error: ' . $e->getMessage())->danger()->send();
                                return;
                            }

                            $record->update([
                                'status' => DisputeStatus::ResolvedMerchant,
                                'resolution_notes' => $data['resolution_notes'],
                                'resolved_by' => auth()->user()?->email . ' (winner: merchant)',
                            ]);

                            $trade?->update([
                                'status' => TradeStatus::Completed,
                                'completed_at' => now(),
                            ]);

                            if ($trade) {
                                TradeCompleted::dispatch($trade);
                            }

                            Notification::make()
                                ->title(__('trade.resolve_for_merchant'))
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (Dispute $record): bool => $record->status !== DisputeStatus::Open),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
