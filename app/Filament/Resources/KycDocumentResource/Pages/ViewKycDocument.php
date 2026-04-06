<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

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
use Illuminate\Support\Facades\Storage;

class ViewKycDocument extends ViewRecord
{
    protected static string $resource = KycDocumentResource::class;

    protected function getHeaderActions(): array
    {
        $pendingDocs = $this->record->kycDocuments()->where('status', KycStatus::Pending)->get();
        $allDocs = $this->record->kycDocuments()->latest()->get();

        $actions = [];

        // Single Approve button with document type selector
        if ($pendingDocs->isNotEmpty()) {
            $actions[] = Action::make('approve')
                ->label(__('kyc.approve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Document')
                ->modalDescription('Select which document to approve.')
                ->schema([
                    Select::make('document_id')
                        ->label(__('kyc.document_type_label'))
                        ->options(
                            $pendingDocs->mapWithKeys(fn ($doc) => [
                                $doc->id => $doc->type->getLabel(),
                            ])->toArray()
                        )
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $doc = KycDocument::find($data['document_id']);
                    if ($doc && $doc->merchant_id === $this->record->id) {
                        $doc->update([
                            'status' => KycStatus::Approved,
                            'reviewed_at' => now(),
                        ]);
                        KycDocumentReviewed::dispatch($doc);
                        Notification::make()->title($doc->type->getLabel() . ' approved')->success()->send();
                        $this->redirect($this->getUrl());
                    }
                });

            // Single Reject button with document type selector + reason
            $actions[] = Action::make('reject')
                ->label(__('kyc.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject Document')
                ->modalDescription('Select which document to reject and provide a reason.')
                ->schema([
                    Select::make('document_id')
                        ->label(__('kyc.document_type_label'))
                        ->options(
                            $pendingDocs->mapWithKeys(fn ($doc) => [
                                $doc->id => $doc->type->getLabel(),
                            ])->toArray()
                        )
                        ->required(),
                    Textarea::make('rejection_reason')
                        ->label(__('kyc.rejection_reason'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $doc = KycDocument::find($data['document_id']);
                    if ($doc && $doc->merchant_id === $this->record->id) {
                        $doc->update([
                            'status' => KycStatus::Rejected,
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_at' => now(),
                        ]);
                        KycDocumentReviewed::dispatch($doc);
                        Notification::make()->title($doc->type->getLabel() . ' rejected')->success()->send();
                        $this->redirect($this->getUrl());
                    }
                });
        }

        // Single Download button with document selector
        $downloadableDocs = $allDocs->filter(fn ($doc) => $doc->file_path && Storage::disk('local')->exists($doc->file_path));
        if ($downloadableDocs->isNotEmpty()) {
            $actions[] = Action::make('download')
                ->label(__('kyc.download_document'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->schema([
                    Select::make('document_id')
                        ->label(__('kyc.document_type_label'))
                        ->options(
                            $downloadableDocs->mapWithKeys(fn ($doc) => [
                                $doc->id => $doc->type->getLabel(),
                            ])->toArray()
                        )
                        ->required(),
                ])
                ->action(function (array $data) {
                    $doc = KycDocument::find($data['document_id']);
                    if ($doc && $doc->merchant_id === $this->record->id && $doc->file_path) {
                        return response()->download(
                            Storage::disk('local')->path($doc->file_path),
                            $doc->original_name ?? basename($doc->file_path)
                        );
                    }
                });
        }

        return $actions;
    }
}
