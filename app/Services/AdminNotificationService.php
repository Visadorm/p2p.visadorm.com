<?php

namespace App\Services;

use App\Models\User;
use App\Settings\NotificationSettings;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class AdminNotificationService
{
    /**
     * Send a Filament database notification + email to all admin users,
     * but only if the corresponding alert toggle is enabled.
     */
    public static function notifyIf(string $settingKey, string $title, string $body, string $icon = 'heroicon-o-bell', string $color = 'warning'): void
    {
        $settings = rescue(fn () => app(NotificationSettings::class), null);

        // Check if the specific alert is enabled (default: true for unknown keys)
        if ($settings && property_exists($settings, $settingKey) && ! $settings->{$settingKey}) {
            return;
        }

        $admins = User::whereNotNull('role')->get();

        $emailGloballyEnabled = $settings && property_exists($settings, 'email_notifications_enabled')
            ? (bool) $settings->email_notifications_enabled
            : true;

        $dedicatedAdminEmail = $settings && property_exists($settings, 'admin_email')
            ? trim((string) $settings->admin_email)
            : '';

        foreach ($admins as $admin) {
            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->icon($icon);

            match ($color) {
                'success' => $notification->success(),
                'danger' => $notification->danger(),
                'info' => $notification->info(),
                default => $notification->warning(),
            };

            $notification->sendToDatabase($admin);
        }

        if (! $emailGloballyEnabled || config('mail.default') === 'log') {
            return;
        }

        $recipients = $dedicatedAdminEmail !== ''
            ? [$dedicatedAdminEmail]
            : $admins->pluck('email')->filter()->unique()->values()->all();

        foreach ($recipients as $email) {
            rescue(fn () => Mail::raw($body, function ($message) use ($email, $title) {
                $message->to($email)->subject("[Visadorm] {$title}");
            }));
        }
    }
}
