<?php

namespace App\Services;

use App\Models\Progress;
use App\Models\Competence;
use App\Models\Learner;

class ScoreCalculatorService
{
    // Calcule le score global pondéré d'un apprenant
    public function calculate(string $learnerId): float
    {
        $competences = Competence::all();
        $progress    = Progress::where('learner_id', $learnerId)->get()->keyBy('competence_id');

        $totalScore  = 0;
        $totalWeight = 0;

        foreach ($competences as $competence) {
            $score       = $progress[$competence->id]->score ?? 0;
            $totalScore  += $score * $competence->weight;
            $totalWeight += $competence->weight;
        }

        if ($totalWeight == 0) return 0;

        return round($totalScore / $totalWeight, 2);
    }

    // Met à jour le score d'une compétence après correction validée
    public function updateCompetenceScore(string $learnerId, string $competenceCode, float $newScore): void
    {
        $competence = Competence::where('code', $competenceCode)->first();
        if (!$competence) return;

        $progress = Progress::where('learner_id', $learnerId)
            ->where('competence_id', $competence->id)
            ->first();

        if ($progress) {
            // Moyenne glissante : 70% ancien score + 30% nouveau score
            $updatedScore = round(($progress->score * 0.7) + ($newScore * 0.3), 2);
            $progress->update(['score' => $updatedScore]);
        }

        // Recalculer le score global
        $this->updateGlobalScore($learnerId);
    }

    // Met à jour le score global dans la table learners
    public function updateGlobalScore(string $learnerId): float
    {
        $score   = $this->calculate($learnerId);
        $learner = Learner::where('user_id', $learnerId)->first();

        if ($learner) {
            $learner->update([
                'global_score'        => $score,
                'is_expert_candidate' => $score >= 80,
            ]);
        }

        return $score;
    }

    // Retourne les scores par compétence avec les détails
    public function getDetailedScores(string $learnerId): array
    {
        $competences = Competence::all();
        $progress    = Progress::where('learner_id', $learnerId)
                               ->with('competence')
                               ->get()
                               ->keyBy('competence_id');

        $details = [];

        foreach ($competences as $competence) {
            $p         = $progress[$competence->id] ?? null;
            $details[] = [
                'competence' => $competence->code,
                'name'       => $competence->name,
                'weight'     => $competence->weight,
                'score'      => $p ? $p->score : 0,
                'level'      => $p ? $p->level : null,
                'contribution' => round(($p ? $p->score : 0) * $competence->weight, 2),
            ];
        }

        return [
            'details'      => $details,
            'global_score' => $this->calculate($learnerId),
            'is_expert'    => $this->calculate($learnerId) >= 80,
        ];
    }
}