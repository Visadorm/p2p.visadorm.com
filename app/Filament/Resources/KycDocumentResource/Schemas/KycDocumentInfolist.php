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
                            ->weight('bold'),

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
                    ->columns(2),

                Section::make(__('p2p.kyc_documents'))
                    ->schema([
                        RepeatableEntry::make('kycDocuments')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('kyc.document_type_label'))
                                    ->badge(),

                                TextEntry::make('status')
                                    ->label(__('p2p.status'))
                                    ->badge(),

                                TextEntry::make('original_name')
                                    ->label(__('kyc.original_name'))
                                    ->placeholder('—'),

                                TextEntry::make('rejection_reason')
                                    ->label(__('kyc.rejection_reason'))
                                    ->placeholder('—'),

                                TextEntry::make('created_at')
                                    ->label(__('kyc.submitted_at'))
                                    ->dateTime(),

                                TextEntry::make('reviewed_at')
                                    ->label(__('kyc.reviewed_at'))
                                    ->dateTime()
                                    ->placeholder('—'),

                                TextEntry::make('file_path')
                                    ->label(__('kyc.view_document'))
                                    ->formatStateUsing(fn () => __('kyc.download_document'))
                                    ->icon(Heroicon::OutlinedArrowDownTray)
                                    ->url(fn ($state) => $state ? route('admin.kyc.download', ['kycDocument' => 0, 'path' => $state]) : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn ($state) => ! empty($state)),
                            ])
                            ->columns(4)
                            ->placeholder('No documents uploaded'),
                    ]),
            ]);
    }
}
