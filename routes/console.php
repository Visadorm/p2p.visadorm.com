<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('trades:expire-stale')->everyFifteenMinutes();
Schedule::command('trades:retry-blockchain')->everyFifteenMinutes();
Schedule::command('gas:check')->hourly();
