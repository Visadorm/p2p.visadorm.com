<?php

namespace App\Filament\Resources\KycDocumentResource\Tables;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KycDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label(__('merchant.username'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('full_name')
                    ->label(__('merchant.full_name'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('wallet_address')
                    ->label(__('p2p.wallet_address'))
                    ->limit(10)
                    ->tooltip(fn ($record) => $record->wallet_address),

                TextColumn::make('id_status')
                    ->label(__('kyc.type.id_document'))
                    ->state(fn ($record) => self::getDocStatus($record, KycDocumentType::IdDocument))
                    ->badge()
                    ->color(fn ($state) => self::statusColor($state)),

                TextColumn::make('bank_status')
                    ->label(__('kyc.type.bank_statement'))
                    ->state(fn ($record) => self::getDocStatus($record, KycDocumentType::BankStatement))
                    ->badge()
                    ->color(fn ($state) => self::statusColor($state)),

                TextColumn::make('residency_status')
                    ->label(__('kyc.type.proof_of_residency'))
                    ->state(fn ($record) => self::getDocStatus($record, KycDocumentType::ProofOfResidency))
                    ->badge()
                    ->color(fn ($state) => self::statusColor($state)),

                TextColumn::make('business_status')
                    ->label(__('kyc.type.business_document'))
                    ->state(fn ($record) => self::getDocStatus($record, KycDocumentType::BusinessDocument))
                    ->badge()
                    ->color(fn ($state) => self::statusColor($state)),
            ])
            ->filters([
                SelectFilter::make('kyc_status')
                    ->label(__('p2p.status'))
                    ->options(KycStatus::class)
                    ->attribute('kyc_status'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    private static function getDocStatus($merchant, KycDocumentType $type): string
    {
        $doc = $merchant->kycDocuments->where('type', $type)->sortByDesc('created_at')->first();

        if (! $doc) {
            return 'Not Uploaded';
        }

        return match ($doc->status) {
            KycStatus::Approved => 'Approved',
            KycStatus::Rejected => 'Rejected',
            KycStatus::Pending => 'Pending',
            default => 'Unknown',
        };
    }

    private static function statusColor(string $state): string
    {
        return match ($state) {
            'Approved' => 'success',
            'Pending' => 'warning',
            'Rejected' => 'danger',
            default => 'gray',
        };
    }
}
