<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Events\KycDocumentReviewed;
use App\Filament\Resources\KycDocumentResource\KycDocumentResource;
use App\Models\KycDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewKycDocument extends ViewRecord
{
    protected static string $resource = KycDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_document')
                ->label(__('kyc.approve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->schema([
                    Select::make('document_type')
                        ->label(__('kyc.document_type_label'))
                        ->options(
                            $this->record->kycDocuments()
                                ->where('status', KycStatus::Pending)
                                ->get()
                                ->mapWithKeys(fn ($doc) => [
                                    $doc->id => $doc->type->getLabel() . ' — ' . ($doc->original_name ?? 'File'),
                                ])
                                ->toArray()
                        )
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $doc = KycDocument::find($data['document_type']);
                    if ($doc && $doc->merchant_id === $this->record->id) {
                        $doc->update([
                            'status' => KycStatus::Approved,
                            'reviewed_at' => now(),
                        ]);
                        KycDocumentReviewed::dispatch($doc);
                        Notification::make()->title(__('kyc.approve'))->success()->send();
                    }
                })
                ->visible(fn () => $this->record->kycDocuments()->where('status', KycStatus::Pending)->exists()),

            Action::make('reject_document')
                ->label(__('kyc.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->schema([
                    Select::make('document_type')
                        ->label(__('kyc.document_type_label'))
                        ->options(
                            $this->record->kycDocuments()
                                ->where('status', KycStatus::Pending)
                                ->get()
                                ->mapWithKeys(fn ($doc) => [
                                    $doc->id => $doc->type->getLabel() . ' — ' . ($doc->original_name ?? 'File'),
                                ])
                                ->toArray()
                        )
                        ->required(),
                    Textarea::make('rejection_reason')
                        ->label(__('kyc.rejection_reason'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $doc = KycDocument::find($data['document_type']);
                    if ($doc && $doc->merchant_id === $this->record->id) {
                        $doc->update([
                            'status' => KycStatus::Rejected,
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_at' => now(),
                        ]);
                        KycDocumentReviewed::dispatch($doc);
                        Notification::make()->title(__('kyc.reject'))->success()->send();
                    }
                })
                ->visible(fn () => $this->record->kycDocuments()->where('status', KycStatus::Pending)->exists()),
        ];
    }
}
