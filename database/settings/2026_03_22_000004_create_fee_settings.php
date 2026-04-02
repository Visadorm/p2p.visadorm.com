<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // P2P Trading Fees
        $this->migrator->add('fees.p2p_fee_percent', 0.2);

        // Visa Service Fees
        $this->migrator->add('fees.visa_cancel_fee_percent', 0.9);
        $this->migrator->add('fees.visa_success_fee_percent', 2.9);

        // Booking Fees
        $this->migrator->add('fees.booking_cancel_fee_percent', 0.9);
        $this->migrator->add('fees.booking_success_fee_percent', 2.9);

        // Lock Period
        $this->migrator->add('fees.fund_lock_hours', 24);
    }
};
