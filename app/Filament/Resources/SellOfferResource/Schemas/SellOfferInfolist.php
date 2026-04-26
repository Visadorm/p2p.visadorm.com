<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellOfferResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SellOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('p2p.section_offer_info'))
                    ->schema([
                        TextEntry::make('slug')
                            ->label(__('p2p.slug'))
                            ->copyable(),

                        TextEntry::make('is_active')
                            ->label(__('p2p.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                            ->color(fn ($state) => $state ? 'success' : 'gray'),

                        IconEntry::make('is_private')
                            ->label(__('p2p.is_private'))
                            ->boolean(),

                        IconEntry::make('require_kyc')
                            ->label(__('p2p.require_kyc'))
                            ->boolean(),

                        TextEntry::make('expires_at')
                            ->label(__('p2p.expires_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label(__('p2p.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make(__('p2p.section_seller'))
                    ->schema([
                        TextEntry::make('seller_wallet')
                            ->label(__('trade.seller_wallet'))
                            ->copyable()
                            ->limit(42),

                        TextEntry::make('seller.username')
                            ->label(__('p2p.merchant'))
                            ->placeholder('—')
                            ->url(fn ($record) => $record->seller_merchant_id
                                ? route('filament.admin.resources.p2p.merchants.view', ['record' => $record->seller_merchant_id])
                                : null),
                    ])
                    ->columns(2),

                Section::make(__('p2p.section_amounts'))
                    ->schema([
                        TextEntry::make('amount_usdc')
                            ->label(__('p2p.amount_total'))
                            ->money('USD'),

                        TextEntry::make('amount_remaining_usdc')
                            ->label(__('p2p.amount_remaining'))
                            ->money('USD'),

                        TextEntry::make('min_trade_usdc')
                            ->label(__('p2p.min_trade'))
                            ->money('USD'),

                        TextEntry::make('max_trade_usdc')
                            ->label(__('p2p.max_trade'))
                            ->money('USD'),

                        TextEntry::make('currency_code')
                            ->label(__('trade.currency_code')),

                        TextEntry::make('fiat_rate')
                            ->label(__('p2p.fiat_rate'))
                            ->numeric(4),
                    ])
                    ->columns(3),

                Section::make(__('p2p.section_payment_methods'))
                    ->schema([
                        TextEntry::make('payment_methods_summary')
                            ->label(__('trade.payment_methods'))
                            ->columnSpanFull()
                            ->placeholder('—')
                            ->getStateUsing(function ($record) {
                                $items = $record->payment_methods ?? [];
                                if (! is_array($items) || count($items) === 0) return null;
                                return collect($items)
                                    ->map(function ($pm) {
                                        if (! is_array($pm)) return '• ' . (string) $pm;
                                        $label = $pm['label'] ?? $pm['provider'] ?? $pm['type'] ?? 'unknown';
                                        $id = $pm['merchant_payment_method_id'] ?? null;
                                        return '• ' . $label . ($id ? ' (id #' . $id . ')' : '');
                                    })
                                    ->implode("\n");
                            }),

                        TextEntry::make('instructions')
                            ->label(__('p2p.instructions'))
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),

                Section::make(__('p2p.section_blockchain'))
                    ->schema([
                        TextEntry::make('trade_id')
                            ->label(__('p2p.on_chain_trade_id'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('fund_tx_hash')
                            ->label(__('p2p.fund_tx_hash'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—')
                            ->url(fn ($record) => $record->fund_tx_hash
                                ? 'https://sepolia.basescan.org/tx/' . $record->fund_tx_hash
                                : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('cancel_tx_hash')
                            ->label(__('p2p.cancel_tx_hash'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—')
                            ->url(fn ($record) => $record->cancel_tx_hash
                                ? 'https://sepolia.basescan.org/tx/' . $record->cancel_tx_hash
                                : null)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),
            ]);
    }
}
