<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Services\EscrowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('p2p:reconcile-merchant-balances {--tolerance=0.01}')]
#[Description('A10: detect divergence between DB-derived and on-chain merchant escrow balances. Logs warnings.')]
class ReconcileMerchantBalances extends Command
{
    public function handle(EscrowService $escrow): int
    {
        $tolerance = (float) $this->option('tolerance');
        $diverged = 0;
        $checked = 0;
        $skipped = 0;

        Merchant::query()
            ->where('is_active', true)
            ->whereNotNull('wallet_address')
            ->orderBy('id')
            ->chunkById(100, function ($merchants) use ($escrow, $tolerance, &$diverged, &$checked, &$skipped) {
                foreach ($merchants as $merchant) {
                    try {
                        $result = $escrow->reconcileBalance($merchant, $tolerance);
                        $checked++;
                        if ($result['chain'] === null) {
                            $skipped++; // RPC failed for this one
                            continue;
                        }
                        if (! $result['ok']) {
                            $diverged++;
                            $this->warn(sprintf(
                                "  ✗ %s db=%.6f chain=%.6f Δ=%.6f",
                                $merchant->wallet_address,
                                $result['db'],
                                $result['chain'],
                                $result['divergence']
                            ));
                        }
                    } catch (Throwable $e) {
                        Log::error('Balance reconciliation failed', [
                            'merchant_id' => $merchant->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Checked: {$checked} | Diverged: {$diverged} | Skipped (RPC): {$skipped}");

        if ($diverged > 0) {
            Log::warning('Escrow reconciliation found divergences', [
                'checked' => $checked,
                'diverged' => $diverged,
                'tolerance' => $tolerance,
            ]);
        }

        return self::SUCCESS;
    }
}
