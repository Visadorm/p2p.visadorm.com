<?php

namespace App\Filament\Resources\KycDocumentResource\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class KycDocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('merchant.section_profile'))
                    ->schema([
                        TextEntry::make('username')
                            ->label(__('merchant.username'))
                            ->weight('bold')
                            ->url(fn ($record) => route('filament.admin.resources.p2p.merchants.view', $record))
                            ->openUrlInNewTab(),

                        TextEntry::make('full_name')
                            ->label(__('merchant.full_name'))
                            ->placeholder('—'),

                        TextEntry::make('business_name')
                            ->label(__('merchant.business_name'))
                            ->placeholder('—'),

                        TextEntry::make('wallet_address')
                            ->label(__('p2p.wallet_address'))
                            ->copyable(),

                        TextEntry::make('email')
                            ->label(__('merchant.email'))
                            ->placeholder('—'),

                        TextEntry::make('kyc_status')
                            ->label(__('kyc.status_label'))
                            ->badge(),
                    ])
                    ->columns(3),

                Section::make(__('p2p.kyc_documents'))
                    ->description('Review and manage uploaded verification documents')
                    ->schema([
                        RepeatableEntry::make('kycDocuments')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('kyc.document_type_label'))
                                    ->badge()
                                    ->size('lg'),

                                TextEntry::make('status')
                                    ->label(__('p2p.status'))
                                    ->badge()
                                    ->size('lg'),

                                TextEntry::make('original_name')
                                    ->label('File')
                                    ->icon(Heroicon::OutlinedDocument)
                                    ->placeholder('—'),

                                TextEntry::make('created_at')
                                    ->label(__('kyc.submitted_at'))
                                    ->dateTime('M d, Y H:i'),

                                TextEntry::make('reviewed_at')
                                    ->label(__('kyc.reviewed_at'))
                                    ->dateTime('M d, Y H:i')
                                    ->placeholder('Not reviewed'),

                                TextEntry::make('rejection_reason')
                                    ->label(__('kyc.rejection_reason'))
                                    ->placeholder('—')
                                    ->color('danger')
                                    ->visible(fn ($state) => ! empty($state)),

                                TextEntry::make('id')
                                    ->label('View Document')
                                    ->formatStateUsing(fn () => 'Open in new tab')
                                    ->icon(Heroicon::OutlinedEye)
                                    ->url(fn ($record) => $record?->id ? route('admin.kyc.download', ['kycDocument' => $record->id]) : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn ($record) => ! empty($record?->file_path)),
                            ])
                            ->columns(3)
                            ->placeholder('No documents uploaded'),
                    ]),
            ]);
    }
}
