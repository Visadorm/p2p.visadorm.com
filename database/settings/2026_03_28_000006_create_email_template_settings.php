<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('email_template.logo_url', '');
        $this->migrator->add('email_template.header_image_url', '');
        $this->migrator->add('email_template.primary_color', '#8288bf');
        $this->migrator->add('email_template.secondary_color', '#f59e0b');
        $this->migrator->add('email_template.footer_text', 'Visadorm P2P — All trades are fully escrowed on the Base blockchain.');
    }
};
