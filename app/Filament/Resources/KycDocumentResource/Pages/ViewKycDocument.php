<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

use App\Enums\KycStatus;
use App\Events\KycDocumentReviewed;
use App\Filament\Resources\KycDocumentResource\KycDocumentResource;
use App\Models\KycDocument;
use Filament\Actions\Action;
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
        $actions = [];

        foreach ($this->record->kycDocuments()->latest()->get() as $doc) {
            $typeLabel = $doc->type->getLabel();
            $docId = $doc->id;

            if ($doc->status === KycStatus::Pending) {
                $actions[] = Action::make("approve_{$docId}")
                    ->label("Approve: {$typeLabel}")
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading("Approve {$typeLabel}")
                    ->modalDescription("Are you sure you want to approve this {$typeLabel}?")
                    ->action(function () use ($doc): void {
                        $doc->update([
                            'status' => KycStatus::Approved,
                            'reviewed_at' => now(),
                        ]);
                        KycDocumentReviewed::dispatch($doc);
                        Notification::make()->title("{$doc->type->getLabel()} approved")->success()->send();
                        $this->redirect($this->getUrl());
                    });

                $actions[] = Action::make("reject_{$docId}")
                    ->label("Reject: {$typeLabel}")
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading("Reject {$typeLabel}")
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label(__('kyc.rejection_reason'))
                            ->required(),
                    ])
                    ->action(function (array $data) use ($doc): void {
                        $doc->update([
                            'status' => KycStatus::Rejected,
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_at' => now(),
                        ]);
                        KycDocumentReviewed::dispatch($doc);
                        Notification::make()->title("{$doc->type->getLabel()} rejected")->success()->send();
                        $this->redirect($this->getUrl());
                    });
            }

            if ($doc->file_path && Storage::disk('local')->exists($doc->file_path)) {
                $actions[] = Action::make("download_{$docId}")
                    ->label("Download: {$typeLabel}")
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->action(function () use ($doc) {
                        return response()->download(
                            Storage::disk('local')->path($doc->file_path),
                            $doc->original_name ?? basename($doc->file_path)
                        );
                    });
            }
        }

        return $actions;
    }
}
