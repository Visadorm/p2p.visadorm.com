<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

use App\Enums\KycStatus;
use App\Filament\Resources\KycDocumentResource\KycDocumentResource;
use App\Models\KycDocument;
use App\Models\Merchant;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListKycDocuments extends ListRecords
{
    protected static string $resource = KycDocumentResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Merchant::whereHas('kycDocuments')->count()),
            'pending' => Tab::make('Pending')
                ->badge(Merchant::whereHas('kycDocuments', fn ($q) => $q->where('status', KycStatus::Pending))->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('kycDocuments', fn ($q) => $q->where('status', KycStatus::Pending))),
            'approved' => Tab::make('Approved')
                ->badge(Merchant::whereHas('kycDocuments', fn ($q) => $q->where('status', KycStatus::Approved))->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('kycDocuments', fn ($q) => $q->where('status', KycStatus::Approved))),
            'rejected' => Tab::make('Rejected')
                ->badge(Merchant::whereHas('kycDocuments', fn ($q) => $q->where('status', KycStatus::Rejected))->count())
                ->modifyQueryUsing(fn ($query) => $query->whereHas('kycDocuments', fn ($q) => $q->where('status', KycStatus::Rejected))),
        ];
    }
}
