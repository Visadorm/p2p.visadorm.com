<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('trades:expire-stale')->everyFifteenMinutes()->after(fn () => Log::info('CRON: trades:expire-stale ran'));
// A9: backend-enforced auto-cancel of expired sell trades (1-min cadence).
Schedule::command('p2p:cancel-expired-sell-trades')->everyMinute()->withoutOverlapping()->after(fn () => Log::info('CRON: p2p:cancel-expired-sell-trades ran'));
// A10: reconcile DB-derived vs on-chain merchant balances; logs divergences.
Schedule::command('p2p:reconcile-merchant-balances')->hourly()->withoutOverlapping()->after(fn () => Log::info('CRON: p2p:reconcile-merchant-balances ran'));
// B3/B4: detect orphaned Completed/Cancelled trades whose blockchain job failed.
Schedule::command('p2p:reconcile-trade-chain-state')->everyTenMinutes()->withoutOverlapping()->after(fn () => Log::info('CRON: p2p:reconcile-trade-chain-state ran'));
Schedule::command('trades:retry-blockchain')->everyFifteenMinutes()->after(fn () => Log::info('CRON: trades:retry-blockchain ran'));
Schedule::command('gas:check')->hourly()->after(fn () => Log::info('CRON: gas:check ran'));

Schedule::call(function () {
    $pending = \DB::table('jobs')->count();
    $failed = \DB::table('failed_jobs')->count();
    Log::info("QUEUE: {$pending} pending, {$failed} failed");
})->everyFiveMinutes();
