<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Network
        $this->migrator->add('blockchain.network', 'base_sepolia');
        $this->migrator->add('blockchain.rpc_url', '');
        $this->migrator->add('blockchain.chain_id', 84532);

        // Contracts (deployed addresses — Base Sepolia)
        $this->migrator->add('blockchain.trade_escrow_address', '0xbAD6b9c9D54a415e5930241327d9Fd70f4D20ed1');
        $this->migrator->add('blockchain.soulbound_nft_address', '0xC31d56C9FfEb857aBB69dd6a686658E3Fd15bB4e');
        $this->migrator->add('blockchain.visa_escrow_address', '');
        $this->migrator->add('blockchain.booking_escrow_address', '');
        $this->migrator->add('blockchain.usdc_address', '0xc4d1c4B5778f61d8DdAB492FEF745FB5133FEC53');

        // Gas Wallet
        $this->migrator->add('blockchain.gas_wallet_address', '0x7e5ca1bb6232c80469237eaea094f21029b800ab');
        $this->migrator->add('blockchain.min_gas_balance', '0.05');

        // Multisig
        $this->migrator->add('blockchain.fee_wallet_address', '0xb0858aa1264d5d5433dac742b2c30abfc7798736');
        $this->migrator->add('blockchain.admin_multisig_address', '0x91511adcbbe32bc5202b73741bcd7adfbf9ab00e');
    }
};
