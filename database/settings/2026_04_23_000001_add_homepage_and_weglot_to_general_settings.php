<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.homepage_variant', 'classic');
        $this->migrator->add('general.weglot_enabled', false);
        $this->migrator->add('general.weglot_api_key', null);
    }
};
