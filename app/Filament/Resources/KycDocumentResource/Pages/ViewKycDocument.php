<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

use App\Filament\Resources\KycDocumentResource\KycDocumentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewKycDocument extends ViewRecord
{
    protected static string $resource = KycDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label(__('kyc.download_document'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->visible(fn () => $this->record->file_path && Storage::disk('local')->exists($this->record->file_path))
                ->action(function () {
                    return response()->download(
                        Storage::disk('local')->path($this->record->file_path),
                        $this->record->original_name
                    );
                }),
        ];
    }
}
