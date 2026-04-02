<?php

namespace App\Console\Commands;

use App\Services\TradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireStaleTrades extends Command
{
    protected $signature = 'trades:expire-stale';

    protected $description = 'Expire trades that have exceeded their timeout window';

    public function handle(TradeService $tradeService): int
    {
        $count = $tradeService->expireStale();

        if ($count > 0) {
            Log::info("Expired {$count} stale trade(s).");
        }

        $this->info("Expired {$count} stale trade(s).");

        return self::SUCCESS;
    }
}
