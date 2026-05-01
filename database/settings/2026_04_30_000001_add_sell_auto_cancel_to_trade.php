<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // A9: enable backend cron auto-cancel of expired sell trades.
        $this->migrator->add('trade.sell_auto_cancel_expired_enabled', true);
    }
};
