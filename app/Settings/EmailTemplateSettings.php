<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EmailTemplateSettings extends Settings
{
    public ?string $logo_path;
    public ?string $header_image_path;
    public string $primary_color;
    public string $secondary_color;
    public string $footer_text;

    public static function group(): string
    {
        return 'email_template';
    }
}
