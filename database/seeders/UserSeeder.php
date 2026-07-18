<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Willem',
            'email' => 'willem@example.com',
        ]);

        User::factory()->create([
            'name' => 'Partner',
            'email' => 'partner@example.com',
        ]);
    }
}
