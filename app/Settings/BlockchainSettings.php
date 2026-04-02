<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BlockchainSettings extends Settings
{
    // Network
    public string $network;
    public string $rpc_url;
    public int $chain_id;

    // Contracts (deployed addresses)
    public string $trade_escrow_address;
    public string $soulbound_nft_address;
    public string $usdc_address;

    // Gas Wallet
    public string $gas_wallet_address;
    public string $min_gas_balance;

    // Multisig
    public string $fee_wallet_address;
    public string $admin_multisig_address;

    public static function group(): string
    {
        return 'blockchain';
    }
}
