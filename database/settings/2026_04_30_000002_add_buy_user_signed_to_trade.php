<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // B1: feature flag for user-signed buy flow. Off by default until
        // contract redeploy + frontend rollout.
        $this->migrator->add('trade.buy_user_signed_enabled', false);
    }
};
