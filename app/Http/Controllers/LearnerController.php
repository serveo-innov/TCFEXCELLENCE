<?php

namespace App\Http\Controllers;

use App\Http\Resources\LearnerDashboardResource;
use App\Models\Learner;
use App\Services\ScoreCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnerController extends Controller
{
    public function __construct(protected ScoreCalculatorService $scoreService)
    {
    }

    /**
     * @OA\Get(
     *     path="/learner/{id}/dashboard",
     *     summary="Dashboard complet de l'apprenant",
     *     tags={"Apprenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Dashboard retourné avec succès"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Apprenant non trouvé")
     * )
     */
    public function dashboard(Request $request, string $id): JsonResponse
    {
        // Un apprenant ne peut voir que son propre dashboard
        if ($request->user()->id !== $id && !$request->user()->hasRole('ADMIN')) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $learner = Learner::with([
            'user',
            'progress.competence',
            'coachMessages.coach.user',
            'appointments',
            'submissions.exercise.competence',
        ])->where('user_id', $id)->firstOrFail();

        // Mettre à jour last_active_at
        $learner->update(['last_active_at' => now()]);

        return response()->json(new LearnerDashboardResource($learner));
    }

    /**
     * @OA\Get(
     *     path="/learner/{id}/progress",
     *     summary="Progression par compétence",
     *     tags={"Apprenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Progression retournée"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Apprenant non trouvé")
     * )
     */
    public function progress(Request $request, string $id): JsonResponse
    {
        if ($request->user()->id !== $id && !$request->user()->hasRole('ADMIN')) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $scores = $this->scoreService->getDetailedScores($id);

        return response()->json($scores);
    }

    /**
     * @OA\Patch(
     *     path="/learner/{id}/exam-date",
     *     summary="Mettre à jour la date d'examen cible",
     *     tags={"Apprenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"target_exam_date"},
     *             @OA\Property(property="target_exam_date", type="string", format="date", example="2026-12-01")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Date mise à jour"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function updateExamDate(Request $request, string $id): JsonResponse
    {
        if ($request->user()->id !== $id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $request->validate([
            'target_exam_date' => 'required|date|after:today',
        ]);

        $learner = Learner::where('user_id', $id)->firstOrFail();

        $learner->update([
            'target_exam_date' => $request->target_exam_date,
        ]);

        return response()->json([
            'message'          => 'Date d\'examen mise à jour.',
            'target_exam_date' => $learner->target_exam_date->toDateString(),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/learner/{id}/hide-banner",
     *     summary="Masquer la bannière d'incitation pour 7 jours",
     *     tags={"Apprenant"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Bannière masquée"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function hideBanner(Request $request, string $id): JsonResponse
    {
        if ($request->user()->id !== $id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $learner = Learner::where('user_id', $id)->firstOrFail();

        $learner->update([
            'banner_hidden_until' => now()->addDays(7),
        ]);

        return response()->json(['message' => 'Bannière masquée pour 7 jours.']);
    }
}
