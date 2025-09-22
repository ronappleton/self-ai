<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['owner', 'operator'];

        $guard = config('auth.defaults.guard', 'web');

        foreach ($roles as $role) {
            Role::query()->firstOrCreate([
                'name' => $role,
                'guard_name' => $guard,
            ]);
        }
    }
}
