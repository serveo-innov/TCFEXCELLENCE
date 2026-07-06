<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\QcmQuestion;
use App\Models\QcmOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    /**
     * @OA\Get(path="/exercises", summary="Liste des exercices", tags={"Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="competence", in="query", @OA\Schema(type="string", enum={"CO","CE","EO","EE"})),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"LESSON","PRACTICE","QCM","AUDIO"})),
     *     @OA\Parameter(name="level", in="query", @OA\Schema(type="string", enum={"A1","A2","B1","B2","C1","C2"})),
     *     @OA\Parameter(name="all", in="query", description="Admin: inclure inactifs", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Exercices retournés")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->hasRole('ADMIN');
        $query   = Exercise::with('competence');

        // Admin peut voir tous les exercices (actifs + inactifs)
        if (!$isAdmin || !$request->boolean('all')) {
            $query->where('is_active', true);
        }

        if ($request->filled('competence')) {
            $query->whereHas('competence', fn($q) => $q->where('code', strtoupper($request->competence)));
        }
        if ($request->filled('type'))  $query->where('type',  strtoupper($request->type));
        if ($request->filled('level')) $query->where('level', strtoupper($request->level));

        $exercises = $query->orderBy('created_at', 'desc')->get()->map(fn($e) => [
            'id'              => $e->id,
            'title'           => $e->title,
            'description'     => $e->description,
            'type'            => $e->type,
            'level'           => $e->level,
            'is_active'       => $e->is_active,
            'competence_id'   => $e->competence_id,
            'competence_code' => $e->competence->code,
            'competence_name' => $e->competence->name,
            'questions_count' => $e->qcmQuestions()->count(),
        ]);

        return response()->json($exercises);
    }

    /**
     * @OA\Get(path="/exercises/{id}", summary="Détail d'un exercice", tags={"Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Exercice retourné"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $isAdmin  = $request->user()->hasRole('ADMIN');
        $query    = Exercise::with(['competence', 'qcmQuestions.options']);

        if (!$isAdmin) $query->where('is_active', true);

        $exercise = $query->findOrFail($id);

        $data = [
            'id'              => $exercise->id,
            'title'           => $exercise->title,
            'description'     => $exercise->description,
            'type'            => $exercise->type,
            'level'           => $exercise->level,
            'content'         => $exercise->content,
            'is_active'       => $exercise->is_active,
            'competence_id'   => $exercise->competence_id,
            'competence_code' => $exercise->competence->code,
            'competence_name' => $exercise->competence->name,
        ];

        if ($exercise->type === 'QCM') {
            $data['questions'] = $exercise->qcmQuestions->map(fn($q) => [
                'id'          => $q->id,
                'question'    => $q->question,
                'points'      => $q->points,
                'explanation' => $q->explanation,
                'options'     => $q->options->map(fn($o) => [
                    'id'         => $o->id,
                    'content'    => $o->content,
                    // is_correct exposé à l'admin uniquement
                    'is_correct' => $isAdmin ? $o->is_correct : null,
                ]),
            ]);
        }

        return response()->json($data);
    }

    /**
     * @OA\Post(path="/admin/exercises", summary="Créer un exercice", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"title","competence_id","type","level"},
     *         @OA\Property(property="title", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="competence_id", type="string"),
     *         @OA\Property(property="type", type="string", enum={"LESSON","PRACTICE","QCM","AUDIO"}),
     *         @OA\Property(property="level", type="string", enum={"A1","A2","B1","B2","C1","C2"}),
     *         @OA\Property(property="content", type="string"),
     *         @OA\Property(property="is_active", type="boolean"),
     *         @OA\Property(property="questions", type="array", @OA\Items(type="object"))
     *     )),
     *     @OA\Response(response=201, description="Exercice créé")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'                             => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'competence_id'                     => 'required|exists:competences,id',
            'type'                              => 'required|in:LESSON,PRACTICE,QCM,AUDIO',
            'level'                             => 'required|in:A1,A2,B1,B2,C1,C2',
            'content'                           => 'nullable|string',
            'is_active'                         => 'boolean',
            'questions'                         => 'nullable|array',
            'questions.*.question'              => 'required_with:questions|string',
            'questions.*.points'                => 'nullable|numeric|min:0',
            'questions.*.explanation'           => 'nullable|string',
            'questions.*.options'               => 'required_with:questions|array|min:2',
            'questions.*.options.*.content'     => 'required|string',
            'questions.*.options.*.is_correct'  => 'required|boolean',
        ]);

        $exercise = DB::transaction(function () use ($request) {
            $exercise = Exercise::create([
                'title'          => $request->title,
                'description'    => $request->description,
                'competence_id'  => $request->competence_id,
                'type'           => $request->type,
                'level'          => $request->level,
                'content'        => $request->content,
                'is_active'      => $request->boolean('is_active', true),
            ]);

            if ($request->type === 'QCM' && $request->filled('questions')) {
                foreach ($request->questions as $qData) {
                    $question = QcmQuestion::create([
                        'exercise_id' => $exercise->id,
                        'question'    => $qData['question'],
                        'points'      => $qData['points'] ?? 1,
                        'explanation' => $qData['explanation'] ?? null,
                    ]);

                    foreach ($qData['options'] as $oData) {
                        QcmOption::create([
                            'qcm_question_id' => $question->id,
                            'content'         => $oData['content'],
                            'is_correct'      => $oData['is_correct'],
                        ]);
                    }
                }
            }

            return $exercise;
        });

        return response()->json(
            $exercise->load(['competence', 'qcmQuestions.options']),
            201
        );
    }

    /**
     * @OA\Put(path="/admin/exercises/{id}", summary="Modifier un exercice", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Exercice modifié"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);

        $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'competence_id'  => 'sometimes|exists:competences,id',
            'type'           => 'sometimes|in:LESSON,PRACTICE,QCM,AUDIO',
            'level'          => 'sometimes|in:A1,A2,B1,B2,C1,C2',
            'content'        => 'nullable|string',
            'is_active'      => 'boolean',
        ]);

        $exercise->update($request->only([
            'title', 'description', 'competence_id', 'type', 'level', 'content', 'is_active'
        ]));

        return response()->json($exercise->load(['competence', 'qcmQuestions.options']));
    }

    /**
     * @OA\Patch(path="/admin/exercises/{id}/toggle", summary="Activer/désactiver un exercice", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Statut modifié")
     * )
     */
    public function toggleActive(string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);
        $exercise->update(['is_active' => !$exercise->is_active]);

        return response()->json([
            'message'   => $exercise->is_active ? 'Exercice activé.' : 'Exercice désactivé.',
            'is_active' => $exercise->is_active,
        ]);
    }

    /**
     * @OA\Delete(path="/admin/exercises/{id}", summary="Supprimer un exercice", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Exercice supprimé"),
     *     @OA\Response(response=409, description="Exercice avec soumissions — suppression refusée")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $exercise = Exercise::withCount('submissions')->findOrFail($id);

        if ($exercise->submissions_count > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer un exercice ayant des soumissions. Désactivez-le à la place.',
            ], 409);
        }

        DB::transaction(function () use ($exercise) {
            $exercise->qcmQuestions()->each(fn($q) => $q->options()->delete());
            $exercise->qcmQuestions()->delete();
            $exercise->delete();
        });

        return response()->json(['message' => 'Exercice supprimé.']);
    }

    // ── QUESTIONS QCM ─────────────────────────────────────────────────────────

    /**
     * @OA\Post(path="/admin/exercises/{id}/questions", summary="Ajouter une question QCM", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=201, description="Question créée")
     * )
     */
    public function storeQuestion(Request $request, string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);

        $request->validate([
            'question'              => 'required|string',
            'points'                => 'nullable|numeric|min:0',
            'explanation'           => 'nullable|string',
            'options'               => 'required|array|min:2',
            'options.*.content'     => 'required|string',
            'options.*.is_correct'  => 'required|boolean',
        ]);

        $question = DB::transaction(function () use ($request, $exercise) {
            $question = QcmQuestion::create([
                'exercise_id' => $exercise->id,
                'question'    => $request->question,
                'points'      => $request->points ?? 1,
                'explanation' => $request->explanation,
            ]);

            foreach ($request->options as $oData) {
                QcmOption::create([
                    'qcm_question_id' => $question->id,
                    'content'         => $oData['content'],
                    'is_correct'      => $oData['is_correct'],
                ]);
            }

            return $question;
        });

        return response()->json($question->load('options'), 201);
    }

    /**
     * @OA\Put(path="/admin/exercises/{id}/questions/{questionId}", summary="Modifier une question QCM", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="questionId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Question modifiée")
     * )
     */
    public function updateQuestion(Request $request, string $id, string $questionId): JsonResponse
    {
        $question = QcmQuestion::where('exercise_id', $id)->findOrFail($questionId);

        $request->validate([
            'question'              => 'sometimes|string',
            'points'                => 'nullable|numeric|min:0',
            'explanation'           => 'nullable|string',
            'options'               => 'nullable|array|min:2',
            'options.*.content'     => 'required_with:options|string',
            'options.*.is_correct'  => 'required_with:options|boolean',
        ]);

        DB::transaction(function () use ($request, $question) {
            $question->update($request->only(['question', 'points', 'explanation']));

            if ($request->filled('options')) {
                $question->options()->delete();
                foreach ($request->options as $oData) {
                    QcmOption::create([
                        'qcm_question_id' => $question->id,
                        'content'         => $oData['content'],
                        'is_correct'      => $oData['is_correct'],
                    ]);
                }
            }
        });

        return response()->json($question->fresh()->load('options'));
    }

    /**
     * @OA\Delete(path="/admin/exercises/{id}/questions/{questionId}", summary="Supprimer une question QCM", tags={"Admin - Exercices"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="questionId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Question supprimée")
     * )
     */
    public function destroyQuestion(string $id, string $questionId): JsonResponse
    {
        $question = QcmQuestion::where('exercise_id', $id)->findOrFail($questionId);
        $question->options()->delete();
        $question->delete();

        return response()->json(['message' => 'Question supprimée.']);
    }
}
