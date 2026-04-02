<?php

namespace App\Filament\Resources\KycDocumentResource\Tables;

use App\Enums\KycStatus;
use App\Events\KycDocumentReviewed;
use App\Models\KycDocument;
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

class KycDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('merchant.username')
                    ->label(__('kyc.merchant'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('type')
                    ->label(__('kyc.document_type_label'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('p2p.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('kyc.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('p2p.status'))
                    ->options(KycStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('approve')
                        ->label(__('kyc.approve'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (KycDocument $record): void {
                            $record->update([
                                'status' => KycStatus::Approved,
                                'reviewed_at' => now(),
                            ]);

                            KycDocumentReviewed::dispatch($record);

                            Notification::make()
                                ->title(__('kyc.approve'))
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (KycDocument $record): bool => $record->status !== KycStatus::Pending),

                    Action::make('reject')
                        ->label(__('kyc.reject'))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('rejection_reason')
                                ->label(__('kyc.rejection_reason'))
                                ->required(),
                        ])
                        ->action(function (KycDocument $record, array $data): void {
                            $record->update([
                                'status' => KycStatus::Rejected,
                                'rejection_reason' => $data['rejection_reason'],
                                'reviewed_at' => now(),
                            ]);

                            KycDocumentReviewed::dispatch($record);

                            Notification::make()
                                ->title(__('kyc.reject'))
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (KycDocument $record): bool => $record->status !== KycStatus::Pending),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
