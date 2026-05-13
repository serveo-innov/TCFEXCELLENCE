<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'ADMIN']);
        Role::firstOrCreate(['name' => 'COACHED']);
        Role::firstOrCreate(['name' => 'SOLO']);
    }
}