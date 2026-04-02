<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->rename('email_template.logo_url', 'email_template.logo_path');
        $this->migrator->rename('email_template.header_image_url', 'email_template.header_image_path');
    }
};
