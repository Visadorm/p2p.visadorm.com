<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Admin Alerts
        $this->migrator->add('notifications.alert_new_dispute', true);
        $this->migrator->add('notifications.alert_kyc_pending', true);
        $this->migrator->add('notifications.alert_low_gas', true);
        $this->migrator->add('notifications.alert_large_trade', true);
        $this->migrator->add('notifications.large_trade_threshold', 10000.0);

        // Email
        $this->migrator->add('notifications.admin_email', 'admin@visadorm.com');
        $this->migrator->add('notifications.email_notifications_enabled', true);
    }
};
