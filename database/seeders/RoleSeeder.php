<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'COACHED']);
        Role::create(['name' => 'SOLO']);
    }
}