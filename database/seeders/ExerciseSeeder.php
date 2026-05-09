<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;
use App\Models\Competence;
use App\Models\User;
use Ramsey\Uuid\Uuid;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@tcf.com')->first();
        $competences = Competence::all()->keyBy('code');

        $exercises = [
            [
                'id'            => Uuid::uuid4()->toString(),
                'title'         => 'Comprehension dialogue quotidien',
                'description'   => 'Ecoutez le dialogue et repondez aux questions.',
                'competence_id' => $competences['CO']->id,
                'type'          => 'PRACTICE',
                'level'         => 'B1',
                'content'       => 'Un dialogue entre deux personnes dans un cafe...',
                'is_active'     => true,
            ],
            [
                'id'            => Uuid::uuid4()->toString(),
                'title'         => 'Lecture article de presse',
                'description'   => 'Lisez l\'article et repondez aux questions.',
                'competence_id' => $competences['CE']->id,
                'type'          => 'PRACTICE',
                'level'         => 'B2',
                'content'       => 'Article sur les nouvelles technologies en France...',
                'is_active'     => true,
            ],
            [
                'id'            => Uuid::uuid4()->toString(),
                'title'         => 'Presentation personnelle',
                'description'   => 'Enregistrez une presentation de 2 minutes.',
                'competence_id' => $competences['EO']->id,
                'type'          => 'PRACTICE',
                'level'         => 'B1',
                'content'       => 'Presentez-vous en parlant de vos loisirs et projets...',
                'is_active'     => true,
            ],
            [
                'id'            => Uuid::uuid4()->toString(),
                'title'         => 'Redaction lettre formelle',
                'description'   => 'Redigez une lettre de motivation en francais.',
                'competence_id' => $competences['EE']->id,
                'type'          => 'PRACTICE',
                'level'         => 'B2',
                'content'       => 'Vous postulez pour un stage dans une entreprise...',
                'is_active'     => true,
            ],
        ];

        foreach ($exercises as $exercise) {
            Exercise::create($exercise);
        }
    }
}