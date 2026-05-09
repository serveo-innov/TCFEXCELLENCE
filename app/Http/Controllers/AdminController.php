<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Learner;
use App\Services\ScoreCalculatorService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected ScoreCalculatorService $scoreService;

    public function __construct(ScoreCalculatorService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    // GET /api/admin/learners — Liste paginée des apprenants
    public function learners(Request $request)
    {
        $learners = Learner::with(['user'])
            ->paginate($request->get('per_page', 10));

        return response()->json($learners);
    }

    // GET /api/admin/learners/{id} — Profil complet d'un apprenant
    public function learnerProfile(string $id)
    {
        $learner = Learner::with(['user', 'progress.competence'])
            ->where('user_id', $id)
            ->firstOrFail();

        $scores = $this->scoreService->getDetailedScores($id);

        return response()->json([
            'learner' => $learner,
            'scores'  => $scores,
        ]);
    }

    // GET /api/admin/learners/export — Export CSV
    public function exportCsv()
    {
        $learners = Learner::with(['user'])->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="apprenants.csv"',
        ];

        $callback = function () use ($learners) {
            $file = fopen('php://output', 'w');

            // En-têtes CSV
            fputcsv($file, [
                'ID', 'Nom', 'Email', 'Type',
                'Pays', 'Niveau estimé', 'Score global',
                'Expert', 'Date examen', 'Créé le'
            ]);

            // Données
            foreach ($learners as $learner) {
                fputcsv($file, [
                    $learner->user_id,
                    $learner->user->name,
                    $learner->user->email,
                    $learner->registration_type,
                    $learner->country,
                    $learner->estimated_level,
                    $learner->global_score,
                    $learner->is_expert_candidate ? 'Oui' : 'Non',
                    $learner->target_exam_date,
                    $learner->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // POST /api/admin/learners/{id}/message — Envoyer un message coach
    public function sendCoachMessage(Request $request, string $id)
    {
        $request->validate([
            'message'   => 'required|string',
            'is_pinned' => 'boolean',
        ]);

        $coach = $request->user()->coach;

        if (!$coach) {
            return response()->json(['message' => 'Vous n\'êtes pas un coach.'], 403);
        }

        $message = \App\Models\CoachMessage::create([
            'learner_id' => $id,
            'coach_id'   => $request->user()->id,
            'message'    => $request->message,
            'is_pinned'  => $request->get('is_pinned', false),
        ]);

        return response()->json([
            'message' => 'Message envoyé avec succès.',
            'data'    => $message,
        ], 201);
    }

    // GET /api/admin/stats — Statistiques globales
    public function stats()
    {
        $totalLearners  = Learner::count();
        $totalSolo      = Learner::where('registration_type', 'SOLO')->count();
        $totalCoached   = Learner::where('registration_type', 'COACHED')->count();
        $totalExperts   = Learner::where('is_expert_candidate', true)->count();
        $avgScore       = Learner::avg('global_score');

        return response()->json([
            'total_learners'  => $totalLearners,
            'total_solo'      => $totalSolo,
            'total_coached'   => $totalCoached,
            'total_experts'   => $totalExperts,
            'average_score'   => round($avgScore, 2),
        ]);
    }
}