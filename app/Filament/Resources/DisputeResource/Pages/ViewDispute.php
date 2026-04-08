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

                    // Only call blockchain if trade was initiated on-chain
                    if ($trade && $trade->escrow_tx_hash) {
                        try {
                            app(BlockchainService::class)->resolveDispute(
                                $trade->trade_hash,
                                $trade->buyer_wallet,
                            );
                        } catch (\Throwable $e) {
                            Log::error('Dispute resolve blockchain error (buyer)', [
                                'dispute_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                            Notification::make()->title('Blockchain error: ' . $e->getMessage())->danger()->send();
                            return;
                        }
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

                    // Only call blockchain if trade was initiated on-chain
                    if ($trade && $trade->escrow_tx_hash) {
                        try {
                            app(BlockchainService::class)->resolveDispute(
                                $trade->trade_hash,
                                $trade->merchant->wallet_address,
                            );
                        } catch (\Throwable $e) {
                            Log::error('Dispute resolve blockchain error (merchant)', [
                                'dispute_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                            Notification::make()->title('Blockchain error: ' . $e->getMessage())->danger()->send();
                            return;
                        }
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

            Action::make('request_evidence')
                ->label('Request Evidence')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('recipient')
                        ->label('Send To')
                        ->options([
                            'buyer' => 'Buyer',
                            'seller' => 'Seller',
                            'both' => 'Both',
                        ])
                        ->required(),
                    Textarea::make('message')
                        ->label('Message')
                        ->placeholder('Please provide additional evidence such as...')
                        ->required()
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $trade = $record->trade;
                    $admin = auth()->user()?->email ?? 'Admin';
                    $timestamp = now()->format('M d, Y H:i');

                    // Store as admin note
                    $existing = $record->resolution_notes ?? '';
                    $newNote = "[{$timestamp} — {$admin}] Evidence requested from {$data['recipient']}: {$data['message']}";
                    $record->update([
                        'resolution_notes' => $existing ? $existing . "\n\n" . $newNote : $newNote,
                    ]);

                    // Store in evidence array so it shows on frontend
                    $evidence = $record->evidence ?? [];
                    $evidence[] = [
                        'uploaded_by' => 'admin',
                        'note' => "Admin request ({$data['recipient']}): {$data['message']}",
                        'uploaded_at' => now()->toISOString(),
                        'original_name' => 'Evidence Request',
                    ];
                    $record->update(['evidence' => $evidence]);

                    $tradeUrl = url("/trade/{$trade->trade_hash}/confirm");
                    $tradeShort = substr($trade->trade_hash, 0, 10);

                    // Send to seller
                    if (in_array($data['recipient'], ['seller', 'both']) && $trade?->merchant) {
                        // P2P notification (shows in frontend notification bell)
                        app(\App\Services\NotificationService::class)->create(
                            $trade->merchant,
                            'new_dispute',
                            'Evidence Requested — You are the Seller',
                            "Admin needs more evidence from you: {$data['message']}",
                            $trade->id,
                        );

                        // Email to seller
                        if ($trade->merchant->email && $trade->merchant->notify_email) {
                            rescue(fn () => \Illuminate\Support\Facades\Mail::raw(
                                "Hello Seller,\n\nThe admin has requested additional evidence from you regarding trade {$tradeShort}.\n\nAdmin message: {$data['message']}\n\nPlease log in and submit your evidence: {$tradeUrl}\n\n— Visadorm P2P",
                                fn ($m) => $m->to($trade->merchant->email)->subject('[Visadorm] Evidence Requested — Trade Dispute')
                            ));
                        }
                    }

                    // Send to buyer
                    if (in_array($data['recipient'], ['buyer', 'both']) && $trade) {
                        $buyerMerchant = \App\Models\Merchant::where('wallet_address', $trade->buyer_wallet)->first();

                        // P2P notification (shows in frontend notification bell)
                        if ($buyerMerchant) {
                            app(\App\Services\NotificationService::class)->create(
                                $buyerMerchant,
                                'new_dispute',
                                'Evidence Requested — You are the Buyer',
                                "Admin needs more evidence from you: {$data['message']}",
                                $trade->id,
                            );
                        }

                        // Email to buyer
                        if ($buyerMerchant?->email && $buyerMerchant->notify_email) {
                            rescue(fn () => \Illuminate\Support\Facades\Mail::raw(
                                "Hello Buyer,\n\nThe admin has requested additional evidence from you regarding trade {$tradeShort}.\n\nAdmin message: {$data['message']}\n\nPlease log in and submit your evidence: {$tradeUrl}\n\n— Visadorm P2P",
                                fn ($m) => $m->to($buyerMerchant->email)->subject('[Visadorm] Evidence Requested — Trade Dispute')
                            ));
                        }
                    }

                    Notification::make()->title('Evidence request sent')->success()->send();
                    $this->refreshFormData(['resolution_notes']);
                })
                ->hidden(fn () => $this->record->status !== \App\Enums\DisputeStatus::Open),
        ];
    }
}
