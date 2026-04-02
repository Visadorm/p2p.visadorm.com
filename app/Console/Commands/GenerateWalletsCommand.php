<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use kornrunner\Ethereum\Address;

class GenerateWalletsCommand extends Command
{
    protected $signature = 'p2p:generate-wallets';

    protected $description = 'Generate 4 Ethereum keypairs and auto-write them to .env and contracts/.env';

    public function handle(): int
    {
        $this->line('');
        $this->line('  Visadorm P2P — Wallet Generation');
        $this->line('  ' . str_repeat('─', 56));

        $deployer = $this->generateKeypair();
        $operator = $this->generateKeypair();
        $fee      = $this->generateKeypair();
        $admin    = $this->generateKeypair();

        $this->writeToLaravelEnv($operator, $admin);
        $this->writeToContractsEnv($deployer, $operator, $fee, $admin);

        $this->displaySummary($deployer, $operator, $fee, $admin);

        $this->newLine();
        $this->warn('  SECURITY: Private keys are now written to your .env files.');
        $this->warn('  Never commit .env or contracts/.env to version control.');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function generateKeypair(): array
    {
        $hex    = bin2hex(random_bytes(32));
        $wallet = new Address($hex);

        return [
            'private_key' => '0x' . $hex,
            'address'     => '0x' . $wallet->get(),
        ];
    }

    private function writeToLaravelEnv(array $operator, array $admin): void
    {
        $path    = base_path('.env');
        $content = file_exists($path) ? file_get_contents($path) : '';

        $content = $this->setEnvKey($content, 'OPERATOR_PRIVATE_KEY', $operator['private_key']);
        $content = $this->setEnvKey($content, 'ADMIN_PRIVATE_KEY',    $admin['private_key']);

        file_put_contents($path, $content);
    }

    private function writeToContractsEnv(array $deployer, array $operator, array $fee, array $admin): void
    {
        $path = base_path('contracts/.env');

        $lines = [
            '# ─── Visadorm P2P — Hardhat / Deployment Keys ' . str_repeat('─', 14),
            '# Generated: ' . now()->toDateString() . '  (php artisan p2p:generate-wallets)',
            '# DO NOT COMMIT this file.',
            '',
            '# ── Deployer ──────────────────────────────────────────────────',
            '# Fund with Base Sepolia ETH before running deploy.js',
            'DEPLOYER_PRIVATE_KEY=' . $deployer['private_key'],
            '',
            '# ── Alchemy RPC ────────────────────────────────────────────────',
            '# Get your key at https://alchemy.com (Base Sepolia + Base Mainnet)',
            'ALCHEMY_API_KEY=',
            '',
            '# ── Constructor Addresses ──────────────────────────────────────',
            '# Passed to TradeEscrowContract constructor during deployment.',
            'OPERATOR_ADDRESS=' . $operator['address'],
            'FEE_WALLET='       . $fee['address'],
            'ADMIN_ADDRESS='    . $admin['address'],
            '',
            '# ── BaseScan Verification ──────────────────────────────────────',
            '# Get from https://basescan.org — used by: npx hardhat verify',
            'BASESCAN_API_KEY=',
            '',
        ];

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * Replace KEY=<anything> in-place, or append to a blockchain section at the end.
     */
    private function setEnvKey(string $content, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $key . '=' . $value, $content);
        }

        if (! str_contains($content, '# ─── Blockchain')) {
            $content = rtrim($content)
                . "\n\n# ─── Blockchain (Visadorm P2P) " . str_repeat('─', 27) . "\n";
        }

        return $content . $key . '=' . $value . "\n";
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function displaySummary(array $deployer, array $operator, array $fee, array $admin): void
    {
        $lbl = fn (string $s) => str_pad($s, 15);

        $this->newLine();
        $this->info('  [1] DEPLOYER WALLET');
        $this->line('      ' . $lbl('Address:')     . $deployer['address']);
        $this->line('      ' . $lbl('Private Key:') . $deployer['private_key']);
        $this->line('      → contracts/.env  DEPLOYER_PRIVATE_KEY');
        $this->line('      → Fund with Base Sepolia ETH before deploying.');

        $this->newLine();
        $this->info('  [2] OPERATOR WALLET');
        $this->line('      ' . $lbl('Address:')     . $operator['address']);
        $this->line('      ' . $lbl('Private Key:') . $operator['private_key']);
        $this->line('      → .env             OPERATOR_PRIVATE_KEY');
        $this->line('      → contracts/.env  OPERATOR_ADDRESS');
        $this->line('      → Fund with Base Sepolia ETH (ongoing gas for trades).');

        $this->newLine();
        $this->info('  [3] FEE WALLET');
        $this->line('      ' . $lbl('Address:') . $fee['address']);
        $this->line('      (No private key — address only receives the 0.2% fee.)');
        $this->line('      → contracts/.env  FEE_WALLET');

        $this->newLine();
        $this->info('  [4] ADMIN WALLET');
        $this->line('      ' . $lbl('Address:')     . $admin['address']);
        $this->line('      ' . $lbl('Private Key:') . $admin['private_key']);
        $this->line('      → .env             ADMIN_PRIVATE_KEY');
        $this->line('      → contracts/.env  ADMIN_ADDRESS');
        $this->line('      → Signs resolveDispute only. Use Gnosis multisig on mainnet.');

        $this->newLine();
        $this->line('  ─── Files Updated ──────────────────────────────────────────');
        $this->line('      .env             OPERATOR_PRIVATE_KEY, ADMIN_PRIVATE_KEY');
        $this->line('      contracts/.env   DEPLOYER_PRIVATE_KEY, OPERATOR_ADDRESS,');
        $this->line('                       FEE_WALLET, ADMIN_ADDRESS');

        $this->newLine();
        $this->line('  ─── Next Steps ─────────────────────────────────────────────');
        $this->line('      1. Add ALCHEMY_API_KEY to contracts/.env');
        $this->line('      2. Fund Deployer + Operator from Base Sepolia faucet');
        $this->line('      3. cd contracts && npx hardhat run scripts/deploy.js --network baseSepolia');
        $this->line('      4. node scripts/update-laravel-abi.js');
        $this->line('      5. Admin → Settings → Blockchain → paste deployed addresses');
    }
}
