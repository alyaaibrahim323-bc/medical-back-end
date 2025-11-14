<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin','doctor','user'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@bondwell.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
