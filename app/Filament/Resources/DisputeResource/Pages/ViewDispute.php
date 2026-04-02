<?php

namespace App\Filament\Resources\DisputeResource\Pages;

use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Events\TradeCompleted;
use App\Filament\Resources\DisputeResource\DisputeResource;
use App\Services\BlockchainService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

class ViewDispute extends ViewRecord
{
    protected static string $resource = DisputeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolve_for_buyer')
                ->label('Resolve for Buyer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Resolve Dispute — Release to Buyer')
                ->modalDescription('This will release the escrowed USDC to the buyer and return their stake.')
                ->form([
                    Textarea::make('resolution_notes')
                        ->label('Resolution Notes')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $trade = $record->trade;

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

                    Notification::make()->title('Dispute resolved for buyer')->success()->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->hidden(fn () => $this->record->status !== DisputeStatus::Open),

            Action::make('resolve_for_merchant')
                ->label('Resolve for Merchant')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Resolve Dispute — Return to Merchant')
                ->modalDescription('This will return the escrowed USDC to the merchant.')
                ->form([
                    Textarea::make('resolution_notes')
                        ->label('Resolution Notes')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $trade = $record->trade;

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

                    Notification::make()->title('Dispute resolved for merchant')->success()->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->hidden(fn () => $this->record->status !== DisputeStatus::Open),

            Action::make('add_note')
                ->label('Add Admin Note')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->form([
                    Textarea::make('note')
                        ->label('Admin Note')
                        ->placeholder('Add internal notes about this dispute...')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $existing = $record->resolution_notes ?? '';
                    $timestamp = now()->format('M d, Y H:i');
                    $admin = auth()->user()?->email ?? 'Admin';
                    $newNote = "[{$timestamp} — {$admin}] {$data['note']}";

                    $record->update([
                        'resolution_notes' => $existing
                            ? $existing . "\n\n" . $newNote
                            : $newNote,
                    ]);

                    Notification::make()->title('Note added')->success()->send();

                    $this->refreshFormData(['resolution_notes']);
                }),
        ];
    }
}
