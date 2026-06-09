<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAiCorrection;
use App\Models\Correction;
use App\Models\Exercise;
use App\Models\Submission;
use App\Services\ScoreCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    public function __construct(protected ScoreCalculatorService $scoreService)
    {
    }

    /**
     * @OA\Post(
     *     path="/submissions",
     *     summary="Soumettre une production (EE ou EO)",
     *     tags={"Soumissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"exercise_id","type"},
     *             @OA\Property(property="exercise_id",  type="string"),
     *             @OA\Property(property="type",         type="string", enum={"TEXT","AUDIO","QCM"}),
     *             @OA\Property(property="content_text", type="string"),
     *         )
     *     ),
     *     @OA\Response(response=201, description="Soumission enregistrée, correction IA en cours"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exercise_id'  => 'required|exists:exercises,id',
            'type'         => 'required|in:TEXT,AUDIO,QCM',
            'content_text' => 'required_if:type,TEXT|nullable|string|max:10000',
            'audio'        => 'required_if:type,AUDIO|nullable|file|mimes:mp3,wav,m4a,ogg|max:10240',
        ]);

        $exercise  = Exercise::with('competence')->findOrFail($request->exercise_id);
        $learnerId = $request->user()->id;

        $audioUrl = null;

        if ($request->type === 'AUDIO' && $request->hasFile('audio')) {
            $path     = $request->file('audio')->store("submissions/{$learnerId}", 's3');
            $audioUrl = Storage::url($path);
        }

        $submission = Submission::create([
            'learner_id'   => $learnerId,
            'exercise_id'  => $request->exercise_id,
            'type'         => $request->type,
            'content_text' => $request->content_text,
            'audio_url'    => $audioUrl,
            'submitted_at' => now(),
            'status'       => 'PENDING',
        ]);

        // Déclencher la correction IA en arrière-plan (EE ou EO uniquement)
        if (in_array($request->type, ['TEXT', 'AUDIO'])) {
            ProcessAiCorrection::dispatch($submission);
        }

        return response()->json([
            'message'    => 'Soumission enregistrée. Correction en cours...',
            'submission' => [
                'id'         => $submission->id,
                'type'       => $submission->type,
                'status'     => $submission->status,
                'created_at' => $submission->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/submissions/{id}/correction",
     *     summary="Voir la correction validée d'une soumission",
     *     tags={"Soumissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Correction retournée"),
     *     @OA\Response(response=404, description="Pas encore corrigé")
     * )
     */
    public function correction(Request $request, string $id): JsonResponse
    {
        $submission = Submission::where('learner_id', $request->user()->id)
            ->findOrFail($id);

        $correction = Correction::where('submission_id', $id)
            ->whereNotNull('corrected_text')
            ->first();

        if (!$correction) {
            return response()->json(['message' => 'Correction pas encore disponible.'], 404);
        }

        return response()->json([
            'submission_id'   => $id,
            'score'           => $correction->score,
            'feedback'        => $correction->feedback,
            'corrected_text'  => $correction->corrected_text,
            'is_ai_assisted'  => $correction->is_ai_assisted,
            'coach_name'      => 'Votre Coach',
            'created_at'      => $correction->created_at->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/submissions/pending",
     *     summary="Liste des soumissions en attente de validation coach",
     *     tags={"Admin - Corrections IA"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Soumissions retournées")
     * )
     */
    public function pendingCorrections(): JsonResponse
    {
        $submissions = Submission::where('status', 'PENDING')
            ->with([
                'learner.user',
                'exercise.competence',
                'correction',
            ])
            ->latest('submitted_at')
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'learner_name'   => $s->learner->user->name,
                'exercise_title' => $s->exercise->title,
                'competence'     => $s->exercise->competence->code,
                'type'           => $s->type,
                'content_text'   => $s->content_text,
                'audio_url'      => $s->audio_url,
                'submitted_at'   => $s->submitted_at?->toIso8601String(),
                'ai_result'      => $s->correction?->ai_raw_result,
                'ai_score'       => $s->correction?->score,
            ]);

        return response()->json($submissions);
    }

    /**
     * @OA\Patch(
     *     path="/admin/submissions/{id}/validate",
     *     summary="Valider ou modifier la correction IA avant envoi à l'apprenant",
     *     tags={"Admin - Corrections IA"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"corrected_text","score","feedback"},
     *             @OA\Property(property="corrected_text", type="string"),
     *             @OA\Property(property="score",          type="number"),
     *             @OA\Property(property="feedback",       type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Correction validée et envoyée à l'apprenant"),
     *     @OA\Response(response=404, description="Soumission non trouvée")
     * )
     */
    public function validateCorrection(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'corrected_text' => 'required|string',
            'score'          => 'required|numeric|min:0|max:100',
            'feedback'       => 'required|string',
        ]);

        $submission = Submission::with('learner', 'exercise.competence')->findOrFail($id);
        $correction = Correction::where('submission_id', $id)->firstOrFail();

        $correction->update([
            'coach_id'       => $request->user()->id,
            'corrected_text' => $request->corrected_text,
            'score'          => $request->score,
            'feedback'       => $request->feedback,
        ]);

        $submission->update(['status' => 'CORRECTED', 'score' => $request->score]);

        // Mettre à jour le score de la compétence
        $this->scoreService->updateGlobalScore($submission->learner_id);

        return response()->json(['message' => 'Correction validée et transmise à l\'apprenant.']);
    }
}
