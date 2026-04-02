<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings;
use App\Settings\BlockchainSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;

class BlockchainSettingsPage extends SettingsPage
{
    protected static ?string $cluster = Settings::class;

    protected static string $settings = BlockchainSettings::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('settings.blockchain');
    }

    public function getTitle(): string
    {
        return __('settings.blockchain');
    }

    public function afterSave(): void
    {
        Cache::forget('blockchain_config');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.network.title'))
                    ->schema([
                        Select::make('network')
                            ->label(__('settings.network.network'))
                            ->options([
                                'base_sepolia' => __('settings.network.base_sepolia'),
                                'base_mainnet' => __('settings.network.base_mainnet'),
                            ])
                            ->required(),
                        TextInput::make('rpc_url')
                            ->label(__('settings.network.rpc_url'))
                            ->required()
                            ->url(),
                        TextInput::make('chain_id')
                            ->label(__('settings.network.chain_id'))
                            ->required()
                            ->numeric(),
                    ])
                    ->columns(2),

                Section::make(__('settings.contracts.title'))
                    ->schema([
                        TextInput::make('trade_escrow_address')
                            ->label(__('settings.contracts.trade_escrow_address'))
                            ->required()
                            ->maxLength(42),
                        TextInput::make('soulbound_nft_address')
                            ->label(__('settings.contracts.soulbound_nft_address'))
                            ->required()
                            ->maxLength(42),
                        TextInput::make('usdc_address')
                            ->label(__('settings.contracts.usdc_address'))
                            ->required()
                            ->maxLength(42),
                    ])
                    ->columns(2),

                Section::make(__('settings.gas.title'))
                    ->schema([
                        TextInput::make('gas_wallet_address')
                            ->label(__('settings.gas.gas_wallet_address'))
                            ->required()
                            ->maxLength(42),
                        TextInput::make('min_gas_balance')
                            ->label(__('settings.gas.min_gas_balance'))
                            ->required()
                            ->numeric(),
                    ])
                    ->columns(2),

                Section::make(__('settings.multisig.title'))
                    ->schema([
                        TextInput::make('fee_wallet_address')
                            ->label(__('settings.multisig.fee_wallet_address'))
                            ->required()
                            ->maxLength(42),
                        TextInput::make('admin_multisig_address')
                            ->label(__('settings.multisig.admin_multisig_address'))
                            ->required()
                            ->maxLength(42),
                    ])
                    ->columns(2),
            ]);
    }
}
