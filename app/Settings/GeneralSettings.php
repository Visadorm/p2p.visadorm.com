<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    // Branding
    public string $site_name;
    public string $site_description;
    public string $support_email;
    public ?string $logo_path;
    public ?string $favicon_path;

    // Regional
    public string $default_currency;
    public string $default_country;

    // Feature Toggles
    public bool $merchant_registration_enabled;
    public bool $p2p_trading_enabled;
    public bool $cash_meetings_enabled;

    public static function group(): string
    {
        return 'general';
    }
}
