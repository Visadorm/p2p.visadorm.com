<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Branding
        $this->migrator->add('general.site_name', 'Visadorm P2P');
        $this->migrator->add('general.site_description', 'Decentralized P2P USDC Trading');
        $this->migrator->add('general.support_email', 'support@visadorm.com');
        $this->migrator->add('general.logo_path', null);
        $this->migrator->add('general.favicon_path', null);

        // Regional
        $this->migrator->add('general.default_currency', 'USD');
        $this->migrator->add('general.default_country', 'US');

        // Feature Toggles
        $this->migrator->add('general.merchant_registration_enabled', true);
        $this->migrator->add('general.p2p_trading_enabled', true);
        $this->migrator->add('general.cash_meetings_enabled', true);
    }
};
