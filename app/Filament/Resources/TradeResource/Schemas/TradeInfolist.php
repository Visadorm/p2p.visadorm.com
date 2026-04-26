<?php

namespace App\Filament\Resources\TradeResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TradeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('trade.section_trade_info'))
                    ->schema([
                        TextEntry::make('trade_hash')
                            ->label(__('trade.trade_hash'))
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('status')
                            ->label(__('p2p.status'))
                            ->badge(),

                        TextEntry::make('type')
                            ->label(__('trade.type_label'))
                            ->badge(),

                        TextEntry::make('payment_method')
                            ->label(__('trade.payment_method')),

                        TextEntry::make('created_at')
                            ->label(__('p2p.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make(__('trade.section_parties'))
                    ->schema([
                        TextEntry::make('merchant.username')
                            ->label(__('trade.merchant'))
                            ->url(fn ($record) => $record->merchant ? route('filament.admin.resources.p2p.merchants.view', $record->merchant) : null),

                        TextEntry::make('merchant.wallet_address')
                            ->label(__('trade.merchant_wallet'))
                            ->copyable()
                            ->limit(20),

                        TextEntry::make('buyer_wallet')
                            ->label(__('trade.buyer_wallet'))
                            ->copyable()
                            ->limit(20),

                        TextEntry::make('tradingLink.slug')
                            ->label(__('trade.trading_link'))
                            ->placeholder(__('p2p.not_configured')),
                    ])
                    ->columns(2),

                Section::make(__('trade.section_amounts'))
                    ->schema([
                        TextEntry::make('amount_usdc')
                            ->label(__('trade.amount_usdc'))
                            ->money('USD'),

                        TextEntry::make('amount_fiat')
                            ->label(__('trade.amount_fiat'))
                            ->numeric(2)
                            ->suffix(fn ($record) => ' ' . $record->currency_code),

                        TextEntry::make('currency_code')
                            ->label(__('trade.currency_code')),

                        TextEntry::make('exchange_rate')
                            ->label(__('trade.exchange_rate'))
                            ->numeric(2),

                        TextEntry::make('fee_amount')
                            ->label(__('trade.fee_amount'))
                            ->money('USD'),

                        TextEntry::make('stake_amount')
                            ->label(__('trade.stake_amount'))
                            ->money('USD'),

                        TextEntry::make('stake_paid_by')
                            ->label(__('trade.stake_paid_by_label'))
                            ->badge()
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make(__('trade.section_verification'))
                    ->schema([
                        TextEntry::make('bank_proof_status')
                            ->label(__('trade.bank_proof'))
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('buyer_id_status')
                            ->label(__('trade.buyer_id'))
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('bank_proof_path')
                            ->label(__('trade.bank_proof_file'))
                            ->placeholder(__('trade.not_uploaded'))
                            ->url(fn ($record) => $record->bank_proof_path
                                ? route('admin.trade.download-bank-proof', $record)
                                : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('buyer_id_path')
                            ->label(__('trade.buyer_id_file'))
                            ->placeholder(__('trade.not_uploaded'))
                            ->url(fn ($record) => $record->buyer_id_path
                                ? route('admin.trade.download-buyer-id', $record)
                                : null)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),

                Section::make(__('trade.section_blockchain'))
                    ->schema([
                        TextEntry::make('escrow_tx_hash')
                            ->label(__('trade.escrow_tx'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—')
                            ->url(fn ($record) => $record->escrow_tx_hash
                                ? 'https://sepolia.basescan.org/tx/' . $record->escrow_tx_hash
                                : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('release_tx_hash')
                            ->label(__('trade.release_tx'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—')
                            ->url(fn ($record) => $record->release_tx_hash
                                ? 'https://sepolia.basescan.org/tx/' . $record->release_tx_hash
                                : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('nft_token_id')
                            ->label(__('trade.nft_token_id'))
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make(__('trade.section_sell_details'))
                    ->schema([
                        TextEntry::make('seller_wallet')
                            ->label(__('trade.seller_wallet'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—'),

                        TextEntry::make('sellOffer.slug')
                            ->label(__('trade.sell_offer'))
                            ->placeholder('—')
                            ->url(fn ($record) => $record->sell_offer_id
                                ? route('filament.admin.resources.p2p.sell-offers.view', ['record' => $record->sell_offer_id])
                                : null),

                        TextEntry::make('seller_payment_snapshot')
                            ->label(__('trade.seller_payment_snapshot'))
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return '—';
                                $arr = is_array($state) ? $state : (array) $state;
                                $label = $arr['label'] ?? $arr['provider'] ?? '';
                                $details = $arr['details'] ?? [];
                                $lines = [$label];
                                foreach ((array) $details as $k => $v) {
                                    $lines[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
                                }
                                if (! empty($arr['safety_note'])) {
                                    $lines[] = '⚠ ' . $arr['safety_note'];
                                }
                                return implode("\n", array_filter($lines));
                            })
                            ->markdown(false),

                        TextEntry::make('release_signature')
                            ->label(__('trade.release_signature'))
                            ->copyable()
                            ->limit(30)
                            ->placeholder('—'),

                        TextEntry::make('release_signature_nonce')
                            ->label(__('trade.release_signature_nonce'))
                            ->placeholder('—'),

                        TextEntry::make('release_signature_deadline')
                            ->label(__('trade.release_signature_deadline'))
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->type?->value === 'sell'),

                Section::make(__('trade.section_meeting'))
                    ->schema([
                        TextEntry::make('meeting_location')
                            ->label(__('trade.meeting_location')),

                        TextEntry::make('meeting_lat')
                            ->label(__('trade.meeting_lat')),

                        TextEntry::make('meeting_lng')
                            ->label(__('trade.meeting_lng')),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->payment_method === 'cash_meeting'),

                Section::make(__('trade.section_timestamps'))
                    ->schema([
                        TextEntry::make('disputed_at')
                            ->label(__('trade.disputed_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('completed_at')
                            ->label(__('trade.completed_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('expires_at')
                            ->label(__('trade.expires_at'))
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('updated_at')
                            ->label(__('p2p.updated_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
