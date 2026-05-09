<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Learner;
use App\Models\Progress;
use App\Models\Competence;
use Ramsey\Uuid\Uuid;

class LearnerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'learner@tcf.com')->first();
        $competences = Competence::all();

        // Créer le profil apprenant
        Learner::create([
            'user_id'           => $user->id,
            'registration_type' => 'SOLO',
            'country'           => 'France',
            'target_exam_date'  => '2026-12-01',
            'estimated_level'   => 'B2',
            'global_score'      => 0,
            'is_expert_candidate' => false,
        ]);

        // Créer les scores de progression par compétence
        foreach ($competences as $competence) {
            Progress::create([
                'id'             => Uuid::uuid4()->toString(),
                'learner_id'     => $user->id,
                'competence_id'  => $competence->id,
                'score'          => rand(40, 85),
                'level'          => 'B1',
            ]);
        }
    }
}