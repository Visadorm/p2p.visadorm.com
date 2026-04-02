<?php

namespace App\Services;

use App\Models\User;
use App\Settings\NotificationSettings;
use Filament\Notifications\Notification;

class AdminNotificationService
{
    /**
     * Send a Filament database notification to all admin users,
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
    }
}
