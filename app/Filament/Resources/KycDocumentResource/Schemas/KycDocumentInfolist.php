<?php

namespace App\Filament\Resources\KycDocumentResource\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class KycDocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('p2p.kyc_document'))
                    ->schema([
                        TextEntry::make('merchant.username')
                            ->label(__('trade.merchant'))
                            ->url(fn ($record) => $record->merchant ? route('filament.admin.resources.p2p.merchants.view', $record->merchant) : null),

                        TextEntry::make('type')
                            ->label(__('kyc.document_type_label'))
                            ->badge(),

                        TextEntry::make('status')
                            ->label(__('p2p.status'))
                            ->badge(),

                        TextEntry::make('original_name')
                            ->label(__('kyc.original_name')),

                        TextEntry::make('rejection_reason')
                            ->label(__('kyc.rejection_reason'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('reviewed_at')
                            ->label(__('kyc.reviewed_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label(__('p2p.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make(__('kyc.view_document'))
                    ->schema([
                        ImageEntry::make('file_path')
                            ->label(__('kyc.document_preview'))
                            ->disk('local')
                            ->visibility('private')
                            ->height(400)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->file_path
                                && Str::endsWith(strtolower($record->file_path), ['.jpg', '.jpeg', '.png', '.gif', '.webp'])
                                && \Storage::disk('local')->exists($record->file_path)),

                        TextEntry::make('file_path')
                            ->label(__('kyc.file_path'))
                            ->formatStateUsing(fn ($record) => $record->original_name ?? basename($record->file_path ?? ''))
                            ->icon(Heroicon::OutlinedDocument)
                            ->columnSpanFull(),

                        TextEntry::make('file_exists')
                            ->label(__('kyc.file_status'))
                            ->state(fn ($record) => $record->file_path && \Storage::disk('local')->exists($record->file_path)
                                ? __('kyc.file_available')
                                : __('kyc.file_not_found'))
                            ->icon(fn ($record) => $record->file_path && \Storage::disk('local')->exists($record->file_path)
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->file_path && \Storage::disk('local')->exists($record->file_path)
                                ? 'success'
                                : 'danger'),
                    ]),
            ]);
    }
}
