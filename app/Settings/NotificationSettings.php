<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class NotificationSettings extends Settings
{
    // Admin Alerts
    public bool $alert_new_dispute;
    public bool $alert_kyc_pending;
    public bool $alert_low_gas;
    public bool $alert_large_trade;
    public float $large_trade_threshold;

    // Email
    public string $admin_email;
    public bool $email_notifications_enabled;

    // SMS (Twilio)
    public bool $sms_notifications_enabled;

    public static function group(): string
    {
        return 'notifications';
    }
}
