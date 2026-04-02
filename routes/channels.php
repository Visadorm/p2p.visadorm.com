<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Trade channels are public — trade status is not sensitive data.
// Both buyer and merchant need to listen for real-time updates.
Broadcast::channel('trade.{tradeHash}', function () {
    return true;
});
