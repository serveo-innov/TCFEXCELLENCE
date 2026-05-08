<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LearnerProfile;
use App\Services\ScoreCalculatorService;
use Illuminate\Http\Request;

class LearnerController extends Controller
{
    protected ScoreCalculatorService $scoreService;

    public function __construct(ScoreCalculatorService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    // GET /api/learner/{id}/dashboard
    public function dashboard(Request $request, int $id)
    {
        $user = User::with(['learnerProfile', 'competenceScores', 'roles'])->findOrFail($id);

        // Vérifie que l'apprenant accède à son propre dashboard
        if ($request->user()->id !== $id && !$request->user()->hasRole('ADMIN')) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $globalScore = $this->scoreService->calculate($id);
        $profile     = $user->learnerProfile;

        // Calcul des jours d'activité
        $daysActive = $profile && $profile->last_activity_at
            ? now()->diffInDays($profile->created_at)
            : 0;

        $showExpertBanner = $this->scoreService->shouldShowExpertBanner($id, $daysActive);

        return response()->json([
            'user'              => $user,
            'global_score'      => $globalScore,
            'show_expert_banner'=> $showExpertBanner,
            'competence_scores' => $user->competenceScores,
            'profile'           => $profile,
        ]);
    }

    // GET /api/learner/{id}/progress
    public function progress(Request $request, int $id)
    {
        if ($request->user()->id !== $id && !$request->user()->hasRole('ADMIN')) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $scores      = \App\Models\CompetenceScore::where('user_id', $id)->get();
        $globalScore = $this->scoreService->calculate($id);

        return response()->json([
            'competence_scores' => $scores,
            'global_score'      => $globalScore,
        ]);
    }

    // PATCH /api/learner/{id}/exam-date
    public function updateExamDate(Request $request, int $id)
    {
        if ($request->user()->id !== $id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $request->validate([
            'exam_target_date' => 'required|date|after:today',
        ]);

        $profile = LearnerProfile::firstOrCreate(['user_id' => $id]);
        $profile->update(['exam_target_date' => $request->exam_target_date]);

        return response()->json([
            'message' => 'Date d\'examen mise à jour.',
            'profile' => $profile,
        ]);
    }
}