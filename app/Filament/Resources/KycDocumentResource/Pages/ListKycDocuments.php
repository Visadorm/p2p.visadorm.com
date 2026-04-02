<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

use App\Enums\KycStatus;
use App\Filament\Resources\KycDocumentResource\KycDocumentResource;
use App\Models\KycDocument;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListKycDocuments extends ListRecords
{
    protected static string $resource = KycDocumentResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(KycDocument::count()),
            'pending' => Tab::make('Pending')
                ->badge(KycDocument::where('status', KycStatus::Pending)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', KycStatus::Pending)),
            'approved' => Tab::make('Approved')
                ->badge(KycDocument::where('status', KycStatus::Approved)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', KycStatus::Approved)),
            'rejected' => Tab::make('Rejected')
                ->badge(KycDocument::where('status', KycStatus::Rejected)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', KycStatus::Rejected)),
        ];
    }
}
