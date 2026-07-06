<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@netreseau.net'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('Admin@1234'),
                'role'     => 'ADMIN',
                'status'   => 'active',
            ]
        );

        $user->assignRole('ADMIN');

        Admin::firstOrCreate(
            ['user_id' => $user->id],
            ['permissions' => ['all']]
        );
    }
}