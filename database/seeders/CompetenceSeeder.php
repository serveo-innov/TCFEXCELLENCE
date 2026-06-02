<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Competence;
use Illuminate\Support\Str;

class CompetenceSeeder extends Seeder
{
    public function run(): void
    {
        $competences = [
            ['code' => 'CO', 'name' => 'Compréhension Orale',  'weight' => 35.00],
            ['code' => 'CE', 'name' => 'Compréhension Écrite', 'weight' => 30.00],
            ['code' => 'EO', 'name' => 'Expression Orale',     'weight' => 20.00],
            ['code' => 'EE', 'name' => 'Expression Écrite',    'weight' => 15.00],
        ];

        foreach ($competences as $data) {
            Competence::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['id' => Str::uuid()->toString()])
            );
        }
    }
}