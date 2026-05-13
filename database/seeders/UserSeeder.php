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
        /**
         * ADMIN
         */
        $admin = User::updateOrCreate(
            [
                'email' => 'admin@tcf.com',
            ],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
            ]
        );

        $admin->assignRole('ADMIN');

        Admin::updateOrCreate(
            [
                'user_id' => $admin->id,
            ],
            [
                'permissions' => json_encode(['all']),
            ]
        );

        /**
         * COACH
         */
        $coach = User::updateOrCreate(
            [
                'email' => 'coach@tcf.com',
            ],
            [
                'name' => 'Coach Marie',
                'password' => Hash::make('password123'),
            ]
        );

        $coach->assignRole('COACHED');

        Coach::updateOrCreate(
            [
                'user_id' => $coach->id,
            ],
            [
                'bio' => 'Coach certifiée TCF avec 5 ans d\'expérience.',
                'expertise' => 'Expression Orale et Écrite',
            ]
        );

        /**
         * APPRENANT
         */
        $learner = User::updateOrCreate(
            [
                'email' => 'learner@tcf.com',
            ],
            [
                'name' => 'Jean Dupont',
                'password' => Hash::make('password123'),
            ]
        );

        $learner->assignRole('SOLO');
    }
}