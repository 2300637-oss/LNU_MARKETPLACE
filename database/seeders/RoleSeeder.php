<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'code' => 'admin',
                'name' => 'Admin',
                'description' => 'Platform administrator',
                'is_system' => true,
            ],
            [
                'code' => 'user',
                'name' => 'User',
                'description' => 'Default student account',
                'is_system' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}

