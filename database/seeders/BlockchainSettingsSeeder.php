<?php

namespace Database\Seeders;

use App\Settings\BlockchainSettings;
use Illuminate\Database\Seeder;

class BlockchainSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $s = app(BlockchainSettings::class);
        $s->rpc_url = env('ALCHEMY_API_KEY')
            ? 'https://base-sepolia.g.alchemy.com/v2/' . env('ALCHEMY_API_KEY')
            : 'https://sepolia.base.org';
        $s->trade_escrow_address = env('TRADE_ESCROW_ADDRESS', '0x24320787EA129E52aDD81B3e72d1AD21D2923a45');
        $s->soulbound_nft_address = env('SOULBOUND_NFT_ADDRESS', '0xA31aaDAef8ED85ea73b4665291b3c4E7ED5F6bb6');
        $s->usdc_address = env('USDC_ADDRESS', '0xb7cDCbeA16D9A3ae36f8307512b29115B8137ffB');
        $s->gas_wallet_address = '0x7e5ca1bb6232c80469237eaea094f21029b800ab';
        $s->fee_wallet_address = env('FEE_WALLET_ADDRESS', '0xb0858aa1264d5d5433dac742b2c30abfc7798736');
        $s->admin_multisig_address = env('ADMIN_MULTISIG_ADDRESS', '0x91511adcbbe32bc5202b73741bcd7adfbf9ab00e');
        $s->save();
    }
}
