<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Trade;
use App\Settings\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellTradeJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(public Trade $trade)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $settings = app(NotificationSettings::class);
        $emailGloballyEnabled = $settings->email_notifications_enabled ?? true;

        if ($emailGloballyEnabled
            && (bool) ($notifiable->notify_email ?? true)
            && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $merchantName = $this->trade->merchant?->username ?? 'Counterparty';

        return (new MailMessage)
            ->subject(__('p2p.notify.sell_trade_joined.subject'))
            ->line(__('p2p.notify.sell_trade_joined.line', [
                'merchant' => $merchantName,
                'amount' => number_format((float) $this->trade->amount_usdc, 2),
                'currency' => $this->trade->currency_code,
            ]))
            ->action(__('p2p.notify.view_trade'), url('/trade/' . $this->trade->trade_hash));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'trade_hash' => $this->trade->trade_hash,
            'merchant_username' => $this->trade->merchant?->username,
            'amount_usdc' => $this->trade->amount_usdc,
            'currency_code' => $this->trade->currency_code,
            'fiat_rate' => $this->trade->exchange_rate,
            'join_tx_hash' => $this->trade->join_tx_hash,
        ];
    }
}
