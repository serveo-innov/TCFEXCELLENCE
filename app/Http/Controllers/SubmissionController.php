<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAiCorrection;
use App\Models\Correction;
use App\Models\Exercise;
use App\Models\QcmQuestion;
use App\Models\QcmOption;
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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exercise_id'  => 'required|exists:exercises,id',
            'type'         => 'required|in:TEXT,AUDIO,QCM',
            'content_text' => 'required_if:type,TEXT|nullable|string|max:10000',
            'audio'        => 'required_if:type,AUDIO|nullable|file|mimes:mp3,wav,m4a,ogg,webm|max:10240',
        ]);

        $exercise  = Exercise::with('competence')->findOrFail($request->exercise_id);
        $learnerId = $request->user()->id;

        // Vérifier si déjà soumis
        $existing = Submission::where('learner_id', $learnerId)
            ->where('exercise_id', $request->exercise_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message'       => 'Vous avez déjà soumis cet exercice.',
                'submission_id' => $existing->id,
                'status'        => $existing->status,
            ], 409);
        }

        $audioUrl = null;
        if ($request->type === 'AUDIO' && $request->hasFile('audio')) {
            $path     = $request->file('audio')->store("submissions/{$learnerId}", 'local');
            $audioUrl = Storage::url($path);
        }

        // Pour QCM : pré-calculer le score mais laisser en PENDING pour validation coach
        $qcmScore = null;
        if ($request->type === 'QCM' && $request->filled('content_text')) {
            $qcmScore = $this->calculateQcmScore($request->exercise_id, $request->content_text);
        }

        $submission = Submission::create([
            'learner_id'   => $learnerId,
            'exercise_id'  => $request->exercise_id,
            'type'         => $request->type,
            'content_text' => $request->content_text,
            'audio_url'    => $audioUrl,
            'submitted_at' => now(),
            'status'       => 'PENDING',
            'score'        => null,
        ]);

        // Pour QCM : créer une correction préliminaire avec le score auto
        // mais laisser en attente de validation coach
        if ($request->type === 'QCM' && $qcmScore !== null) {
            Correction::create([
                'submission_id'  => $submission->id,
                'is_ai_assisted' => false,
                'score'          => $qcmScore,
                'feedback'       => null,
                'corrected_text' => null,
                'ai_raw_result'  => [
                    'type'    => 'QCM',
                    'score'   => $qcmScore,
                    'message' => 'Score QCM calculé automatiquement. En attente de validation coach.',
                ],
            ]);
        }

        // Déclencher la correction IA pour TEXT et AUDIO
        if (in_array($request->type, ['TEXT', 'AUDIO'])) {
            ProcessAiCorrection::dispatch($submission);
        }

        return response()->json([
            'message'    => 'Soumission enregistrée. En attente de validation.',
            'submission' => [
                'id'         => $submission->id,
                'type'       => $submission->type,
                'status'     => $submission->status,
                'created_at' => $submission->created_at->toIso8601String(),
            ],
        ], 201);
    }

    private function calculateQcmScore(string $exerciseId, string $answersJson): float
    {
        $answers = json_decode($answersJson, true);
        if (!$answers) return 0;

        $questions = QcmQuestion::where('exercise_id', $exerciseId)
            ->with('options')
            ->get();

        if ($questions->isEmpty()) return 0;

        $correct = 0;
        foreach ($questions as $question) {
            $selectedOptionId = $answers[$question->id] ?? null;
            if (!$selectedOptionId) continue;
            $correctOption = $question->options->firstWhere('is_correct', true);
            if ($correctOption && $correctOption->id === $selectedOptionId) {
                $correct++;
            }
        }

        return round(($correct / $questions->count()) * 100, 2);
    }

    public function correction(Request $request, string $id): JsonResponse
    {
        $submission = Submission::where('learner_id', $request->user()->id)
            ->findOrFail($id);

        $correction = Correction::where('submission_id', $id)
            ->whereNotNull('score')
            ->first();

        if (!$correction) {
            return response()->json(['message' => 'Correction pas encore disponible.'], 404);
        }

        return response()->json([
            'submission_id'  => $id,
            'score'          => $correction->score,
            'feedback'       => $correction->feedback,
            'corrected_text' => $correction->corrected_text,
            'is_ai_assisted' => $correction->is_ai_assisted,
            'coach_name'     => 'Votre Coach',
            'created_at'     => $correction->created_at->toIso8601String(),
        ]);
    }

    public function pendingCorrections(): JsonResponse
    {
        $submissions = Submission::where('status', 'PENDING')
            ->with(['learner.user', 'exercise.competence', 'correction'])
            ->latest('submitted_at')
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'learner_name'   => $s->learner->user->name,
                'exercise_title' => $s->exercise->title,
                'competence'     => $s->exercise->competence->code,
                'type'           => $s->type,
                'content_text'   => $this->formatContentText($s),
                'audio_url'      => $s->audio_url,
                'submitted_at'   => $s->submitted_at?->toIso8601String(),
                'ai_result'      => $s->correction?->ai_raw_result,
                'ai_score'       => $s->correction?->score,
            ]);

        return response()->json($submissions);
    }

    private function formatContentText(Submission $s): ?string
    {
        if ($s->type !== 'QCM' || !$s->content_text) {
            return $s->content_text;
        }

        $answers = json_decode($s->content_text, true);
        if (!$answers) return $s->content_text;

        $questions = QcmQuestion::where('exercise_id', $s->exercise_id)
            ->with('options')
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($answers as $questionId => $optionId) {
            $question = $questions[$questionId] ?? null;
            if (!$question) continue;

            $selectedOption = $question->options->firstWhere('id', $optionId);
            $correctOption  = $question->options->firstWhere('is_correct', true);
            $isCorrect      = $selectedOption?->id === $correctOption?->id;

            $lines[] = ($isCorrect ? '✅' : '❌') . ' ' . $question->question
                . "\n   Réponse : " . ($selectedOption?->content ?? '—')
                . ($isCorrect ? '' : "\n   Correct  : " . ($correctOption?->content ?? '—'));
        }

        return implode("\n\n", $lines);
    }

    /**
     * Déclencher manuellement la correction IA (si elle a échoué ou n'a pas tourné)
     */
    public function triggerAiCorrection(string $id): JsonResponse
    {
        $submission = Submission::with('exercise.competence')->findOrFail($id);

        if (!in_array($submission->type, ['TEXT', 'AUDIO'])) {
            return response()->json(['message' => 'Correction IA non applicable pour ce type.'], 422);
        }

        ProcessAiCorrection::dispatch($submission);

        return response()->json(['message' => 'Correction IA relancée. Rechargez dans quelques secondes.']);
    }

    public function validateCorrection(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'corrected_text' => 'required|string',
            'score'          => 'required|numeric|min:0|max:100',
            'feedback'       => 'required|string',
        ]);

        $submission = Submission::with('learner', 'exercise.competence')->findOrFail($id);
        $correction = Correction::where('submission_id', $id)->firstOrNew(['submission_id' => $id]);

        $correction->fill([
            'coach_id'       => $request->user()->id,
            'corrected_text' => $request->corrected_text,
            'score'          => $request->score,
            'feedback'       => $request->feedback,
        ])->save();

        $submission->update(['status' => 'CORRECTED', 'score' => $request->score]);

        $this->scoreService->updateCompetenceScore(
            $submission->learner_id,
            $submission->exercise->competence->code,
            $request->score
        );

        return response()->json(['message' => 'Correction validée et transmise à l\'apprenant.']);
    }
}
