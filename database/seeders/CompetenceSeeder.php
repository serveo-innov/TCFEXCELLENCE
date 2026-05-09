<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Competence;
use Ramsey\Uuid\Uuid;

class CompetenceSeeder extends Seeder
{
    public function run(): void
    {
        $competences = [
            [
                'id'          => Uuid::uuid4()->toString(),
                'code'        => 'CO',
                'name'        => 'Compréhension Orale',
                'description' => 'Capacité à comprendre des documents sonores en français.',
                'weight'      => 0.35,
            ],
            [
                'id'          => Uuid::uuid4()->toString(),
                'code'        => 'CE',
                'name'        => 'Compréhension Écrite',
                'description' => 'Capacité à comprendre des documents écrits en français.',
                'weight'      => 0.30,
            ],
            [
                'id'          => Uuid::uuid4()->toString(),
                'code'        => 'EO',
                'name'        => 'Expression Orale',
                'description' => 'Capacité à s\'exprimer oralement en français.',
                'weight'      => 0.20,
            ],
            [
                'id'          => Uuid::uuid4()->toString(),
                'code'        => 'EE',
                'name'        => 'Expression Écrite',
                'description' => 'Capacité à s\'exprimer par écrit en français.',
                'weight'      => 0.15,
            ],
        ];

        foreach ($competences as $competence) {
            Competence::create($competence);
        }
    }
}