<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $owner = User::factory()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
        ]);

        $owner->assignRole('owner');

        $operator = User::factory()->create([
            'name' => 'Operator User',
            'email' => 'operator@example.com',
        ]);

        $operator->assignRole('operator');
    }
}
