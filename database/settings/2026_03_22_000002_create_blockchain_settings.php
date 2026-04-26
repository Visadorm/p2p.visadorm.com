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
        $this->migrator->add('blockchain.trade_escrow_address', '0xD9771DF5f6EA84AceeA98F6DF27497c159dd940c');
        $this->migrator->add('blockchain.soulbound_nft_address', '0xD81a5b95550E94C7ec995af6BaaD4ab7281B5FFD');
        $this->migrator->add('blockchain.visa_escrow_address', '');
        $this->migrator->add('blockchain.booking_escrow_address', '');
        $this->migrator->add('blockchain.usdc_address', '0xe3B1038eecea95053256D0e5d52D11A0703D1c4F');

        // Gas Wallet
        $this->migrator->add('blockchain.gas_wallet_address', '0x7e5ca1bb6232c80469237eaea094f21029b800ab');
        $this->migrator->add('blockchain.min_gas_balance', '0.05');

        // Multisig
        $this->migrator->add('blockchain.fee_wallet_address', '0xb0858aa1264d5d5433dac742b2c30abfc7798736');
        $this->migrator->add('blockchain.admin_multisig_address', '0x91511adcbbe32bc5202b73741bcd7adfbf9ab00e');
    }
};
