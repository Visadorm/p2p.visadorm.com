<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('trades:expire-stale')->everyFifteenMinutes()->after(fn () => Log::info('CRON: trades:expire-stale ran'));
Schedule::command('trades:retry-blockchain')->everyFifteenMinutes()->after(fn () => Log::info('CRON: trades:retry-blockchain ran'));
Schedule::command('gas:check')->hourly()->after(fn () => Log::info('CRON: gas:check ran'));

Schedule::call(function () {
    $pending = \DB::table('jobs')->count();
    $failed = \DB::table('failed_jobs')->count();
    Log::info("QUEUE: {$pending} pending, {$failed} failed");
})->everyFiveMinutes();
