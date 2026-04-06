<?php

namespace App\Filament\Resources\KycDocumentResource;

use App\Filament\Resources\KycDocumentResource\Pages\ListKycDocuments;
use App\Filament\Resources\KycDocumentResource\Pages\ViewKycDocument;
use App\Filament\Resources\KycDocumentResource\Schemas\KycDocumentInfolist;
use App\Filament\Resources\KycDocumentResource\Tables\KycDocumentsTable;
use App\Models\Merchant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KycDocumentResource extends Resource
{
    protected static ?string $model = Merchant::class;

    protected static ?string $recordTitleAttribute = 'username';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'verification/kyc-documents';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.verification');
    }

    public static function getModelLabel(): string
    {
        return __('p2p.kyc_document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('p2p.kyc_documents');
    }

    public static function infolist(Schema $schema): Schema
    {
        return KycDocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KycDocumentsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin', 'kyc_reviewer']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Merchant::whereHas('kycDocuments')->with('kycDocuments');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKycDocuments::route('/'),
            'view' => ViewKycDocument::route('/{record}'),
        ];
    }
}
