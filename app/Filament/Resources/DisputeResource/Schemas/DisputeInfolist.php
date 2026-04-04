<?php

namespace App\Filament\Resources\DisputeResource\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DisputeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('trade.dispute'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('p2p.status'))
                            ->badge(),

                        TextEntry::make('opened_by')
                            ->label(__('trade.opened_by'))
                            ->copyable()
                            ->formatStateUsing(function ($state, $record) {
                                if (! $state || ! $record->trade) {
                                    return $state;
                                }
                                $trade = $record->trade;
                                $trade->loadMissing('merchant');
                                $wallet = strtolower($state);
                                $role = $wallet === strtolower($trade->buyer_wallet) ? 'Buyer' : 'Seller';
                                $merchant = \App\Models\Merchant::where('wallet_address', $state)->first();
                                $username = $merchant?->username ?? substr($state, 0, 10) . '...';
                                return "{$username} ({$role})";
                            }),

                        TextEntry::make('reason')
                            ->label(__('trade.reason'))
                            ->columnSpanFull(),

                        TextEntry::make('resolution_notes')
                            ->label(__('trade.resolution_notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('resolved_by')
                            ->label(__('trade.resolved_by'))
                            ->placeholder('—'),

                        TextEntry::make('resolution_tx_hash')
                            ->label(__('trade.resolution_tx'))
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label(__('p2p.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Evidence')
                    ->schema([
                        RepeatableEntry::make('evidence')
                            ->schema([
                                TextEntry::make('original_name')
                                    ->label('File'),
                                TextEntry::make('uploaded_by')
                                    ->label('Uploaded By')
                                    ->formatStateUsing(function ($state, $component) {
                                        $record = $component->getContainer()->getParentComponent()->getRecord();
                                        $trade = $record->trade;
                                        if (! $trade || ! $state) {
                                            return $state;
                                        }
                                        if (strtolower($state) === strtolower($trade->buyer_wallet)) {
                                            return 'Buyer';
                                        }
                                        if (strtolower($state) === strtolower($trade->merchant?->wallet_address)) {
                                            return 'Seller';
                                        }
                                        return substr($state, 0, 10) . '...';
                                    })
                                    ->badge()
                                    ->color(function ($state, $component) {
                                        $record = $component->getContainer()->getParentComponent()->getRecord();
                                        $trade = $record->trade;
                                        if ($trade && strtolower($state ?? '') === strtolower($trade->buyer_wallet)) {
                                            return 'info';
                                        }
                                        return 'warning';
                                    }),
                                TextEntry::make('uploaded_at')
                                    ->label('Date')
                                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('M d, Y H:i') : '—'),
                                TextEntry::make('file_path')
                                    ->label('Actions')
                                    ->formatStateUsing(fn () => 'View / Download')
                                    ->url(fn ($state) => $state ? route('admin.dispute.download-evidence', ['path' => $state]) : null)
                                    ->openUrlInNewTab(),
                                TextEntry::make('note')
                                    ->label('Note')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->placeholder('No evidence uploaded'),
                    ]),

                Section::make('Trade Proof Documents')
                    ->schema([
                        TextEntry::make('trade.bank_proof_path')
                            ->label('Bank Proof')
                            ->formatStateUsing(fn ($state) => $state ? 'View Bank Proof' : 'Not uploaded')
                            ->url(fn ($record) => $record->trade?->bank_proof_path
                                ? route('admin.trade.download-bank-proof', $record->trade)
                                : null)
                            ->openUrlInNewTab()
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),

                        TextEntry::make('trade.buyer_id_path')
                            ->label('Buyer ID')
                            ->formatStateUsing(fn ($state) => $state ? 'View Buyer ID' : 'Not submitted')
                            ->url(fn ($record) => $record->trade?->buyer_id_path
                                ? route('admin.trade.download-buyer-id', $record->trade)
                                : null)
                            ->openUrlInNewTab()
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),

                        TextEntry::make('trade.bank_proof_status')
                            ->label('Proof Status')
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('trade.buyer_id_status')
                            ->label('ID Status')
                            ->badge()
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make(__('trade.section_trade_info'))
                    ->schema([
                        TextEntry::make('trade.trade_hash')
                            ->label(__('trade.trade_hash'))
                            ->copyable()
                            ->url(fn ($record) => $record->trade ? route('filament.admin.resources.p2p.trades.view', $record->trade) : null),

                        TextEntry::make('trade.status')
                            ->label(__('trade.trade_status'))
                            ->badge(),

                        TextEntry::make('trade.amount_usdc')
                            ->label(__('trade.amount_usdc'))
                            ->money('USD'),

                        TextEntry::make('trade.merchant.username')
                            ->label(__('trade.merchant')),

                        TextEntry::make('trade.buyer_wallet')
                            ->label(__('trade.buyer_wallet'))
                            ->copyable()
                            ->limit(20),

                        TextEntry::make('trade.payment_method')
                            ->label(__('trade.payment_method')),
                    ])
                    ->columns(2),
            ]);
    }
}
