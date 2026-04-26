<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

$cleanupMinutes = rescue(fn () => max(1, (int) app(\App\Settings\TradeSettings::class)->trade_expiry_cleanup_minutes), 15);
$cleanupCron = '*/' . min(59, $cleanupMinutes) . ' * * * *';

Schedule::command('trades:expire-stale')->cron($cleanupCron)->after(fn () => Log::info('CRON: trades:expire-stale ran'));
Schedule::command('trades:cancel-expired-sell')->cron($cleanupCron)->after(fn () => Log::info('CRON: trades:cancel-expired-sell ran'));
Schedule::command('trades:retry-blockchain')->everyFifteenMinutes()->after(fn () => Log::info('CRON: trades:retry-blockchain ran'));
Schedule::command('gas:check')->hourly()->after(fn () => Log::info('CRON: gas:check ran'));
Schedule::command('trades:reconcile-sell')->everyTenMinutes()->after(fn () => Log::info('CRON: trades:reconcile-sell ran'));

Schedule::call(function () {
    $pending = \DB::table('jobs')->count();
    $failed = \DB::table('failed_jobs')->count();
    Log::info("QUEUE: {$pending} pending, {$failed} failed");
})->everyFiveMinutes();
