<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('trade.sell_enabled', true);
        $this->migrator->add('trade.sell_cash_trade_enabled', true);
        $this->migrator->add('trade.sell_default_expiry_minutes', 60);
        $this->migrator->add('trade.sell_anti_spam_stake_usdc', 5);
        $this->migrator->add('trade.sell_require_stake_public', true);
        $this->migrator->add('trade.sell_require_stake_link', false);
        $this->migrator->add('trade.sell_require_stake_cash', true);
    }
};
