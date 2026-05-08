<?php

namespace App\Services;

use App\Models\CompetenceScore;

class ScoreCalculatorService
{
    // Pondérations configurables
    protected array $weights;

    public function __construct()
    {
        $this->weights = config('tcf.score_weights', [
            'CO' => 0.35,
            'CE' => 0.30,
            'EO' => 0.20,
            'EE' => 0.15,
        ]);
    }

    // Calcule le score global d'un apprenant
    public function calculate(int $userId): float
    {
        $scores = CompetenceScore::where('user_id', $userId)
            ->pluck('score', 'competence');

        $total = 0;

        foreach ($this->weights as $competence => $weight) {
            $total += ($scores[$competence] ?? 0) * $weight;
        }

        return round($total, 2);
    }

    // Vérifie si l'apprenant est éligible au statut Expert
    public function isExpertEligible(int $userId): bool
    {
        return $this->calculate($userId) >= 80;
    }

    // Vérifie si la bannière Expert doit s'afficher
    public function shouldShowExpertBanner(int $userId, int $daysActive): bool
    {
        return $this->calculate($userId) >= 70 && $daysActive >= 14;
    }
}