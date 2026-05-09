<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use App\Models\Coach;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Créer un Admin
        $admin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@tcf.com',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('ADMIN');
        Admin::create([
            'user_id'     => $admin->id,
            'permissions' => ['all'],
        ]);

        // Créer un Coach
        $coach = User::create([
            'name'     => 'Coach Marie',
            'email'    => 'coach@tcf.com',
            'password' => Hash::make('password123'),
        ]);
        $coach->assignRole('COACHED');
        Coach::create([
            'user_id'   => $coach->id,
            'bio'       => 'Coach certifiée TCF avec 5 ans d\'expérience.',
            'expertise' => 'Expression Orale et Écrite',
        ]);

        // Créer un Apprenant
        $learner = User::create([
            'name'     => 'Jean Dupont',
            'email'    => 'learner@tcf.com',
            'password' => Hash::make('password123'),
        ]);
        $learner->assignRole('SOLO');
    }
}