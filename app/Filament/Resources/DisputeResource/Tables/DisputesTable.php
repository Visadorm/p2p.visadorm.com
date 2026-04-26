<?php

namespace App\Filament\Resources\DisputeResource\Tables;

use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Events\TradeCompleted;
use App\Models\Dispute;
use App\Models\Trade;
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

                TextColumn::make('trade.type')
                    ->label(__('trade.type_label'))
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

                SelectFilter::make('trade_type')
                    ->label(__('trade.type_label'))
                    ->options(TradeType::class)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('trade', fn ($q) => $q->where('type', $data['value']))
                        : $query),
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

                            try {
                                self::callContractResolve($trade, $trade->buyer_wallet);
                            } catch (\Throwable $e) {
                                Log::error('Dispute resolve blockchain error (buyer)', [
                                    'dispute_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                                Notification::make()->title('Blockchain error: ' . $e->getMessage())->danger()->send();
                                return;
                            }

                            $record->update([
                                'status' => DisputeStatus::ResolvedBuyer,
                                'resolution_notes' => $data['resolution_notes'],
                                'resolved_by' => auth()->user()?->email . ' (winner: buyer)',
                            ]);

                            $trade?->update([
                                'status' => TradeStatus::Completed,
                                'completed_at' => now(),
                            ]);

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
                            $usdcHolderWallet = $trade?->type === TradeType::Sell
                                ? $trade->seller_wallet
                                : $trade?->merchant?->wallet_address;

                            try {
                                self::callContractResolve($trade, $usdcHolderWallet);
                            } catch (\Throwable $e) {
                                Log::error('Dispute resolve blockchain error (merchant/seller)', [
                                    'dispute_id' => $record->id,
                                    'error' => $e->getMessage(),
                                ]);
                                Notification::make()->title('Blockchain error: ' . $e->getMessage())->danger()->send();
                                return;
                            }

                            $record->update([
                                'status' => DisputeStatus::ResolvedMerchant,
                                'resolution_notes' => $data['resolution_notes'],
                                'resolved_by' => auth()->user()?->email . ' (winner: ' . ($trade?->type === TradeType::Sell ? 'seller' : 'merchant') . ')',
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

    private static function callContractResolve(?Trade $trade, ?string $winnerWallet): void
    {
        if (! $trade || ! $winnerWallet) {
            return;
        }

        $blockchain = app(BlockchainService::class);

        if ($trade->type === TradeType::Sell) {
            $blockchain->resolveSellDispute($trade->trade_hash, $winnerWallet);
            return;
        }

        $blockchain->resolveDispute($trade->trade_hash, $winnerWallet);
    }
}
