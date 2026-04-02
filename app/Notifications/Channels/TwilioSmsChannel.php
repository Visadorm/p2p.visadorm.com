<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Twilio\Rest\Client;

class TwilioSmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toSms($notifiable);

        $phone = $notifiable->phone ?? null;
        if (! $phone) {
            return;
        }

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            return; // Twilio not configured — skip silently
        }

        $client = new Client($sid, $token);
        $client->messages->create($phone, [
            'from' => $from,
            'body' => $message,
        ]);
    }
}
