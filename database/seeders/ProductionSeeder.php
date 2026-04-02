<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@visadorm.com'],
            [
                'name' => 'Visadorm Admin',
                'password' => env('ADMIN_PASSWORD', 'change-me-immediately'),
                'role' => User::ROLE_SUPER_ADMIN,
                'wallet_address' => env('ADMIN_WALLET_ADDRESS'),
            ]
        );
    }
}
