<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/exercises",
     *     summary="Liste des exercices disponibles",
     *     tags={"Exercices"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="competence", in="query", @OA\Schema(type="string", enum={"CO","CE","EO","EE"})),
     *     @OA\Parameter(name="type",       in="query", @OA\Schema(type="string", enum={"LESSON","PRACTICE","QCM"})),
     *     @OA\Parameter(name="level",      in="query", @OA\Schema(type="string", enum={"A1","A2","B1","B2","C1","C2"})),
     *     @OA\Response(response=200, description="Exercices retournés")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Exercise::with('competence')->where('is_active', 1);

        if ($request->filled('competence')) {
            $query->whereHas('competence', fn($q) => $q->where('code', strtoupper($request->competence)));
        }

        if ($request->filled('type')) {
            $query->where('type', strtoupper($request->type));
        }

        if ($request->filled('level')) {
            $query->where('level', strtoupper($request->level));
        }

        $exercises = $query->get()->map(fn($e) => [
            'id'             => $e->id,
            'title'          => $e->title,
            'description'    => $e->description,
            'type'           => $e->type,
            'level'          => $e->level,
            'competence_code'=> $e->competence->code,
            'competence_name'=> $e->competence->name,
        ]);

        return response()->json($exercises);
    }

    /**
     * @OA\Get(
     *     path="/exercises/{id}",
     *     summary="Détail d'un exercice avec ses questions QCM",
     *     tags={"Exercices"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Exercice retourné"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $exercise = Exercise::with(['competence', 'qcmQuestions.options'])
            ->where('is_active', true)
            ->findOrFail($id);

        $data = [
            'id'             => $exercise->id,
            'title'          => $exercise->title,
            'description'    => $exercise->description,
            'type'           => $exercise->type,
            'level'          => $exercise->level,
            'content'        => $exercise->content,
            'competence_code'=> $exercise->competence->code,
            'competence_name'=> $exercise->competence->name,
        ];

        if ($exercise->type === 'QCM') {
            $data['questions'] = $exercise->qcmQuestions->map(fn($q) => [
                'id'          => $q->id,
                'question'    => $q->question,
                'points'      => $q->points,
                'explanation' => $q->explanation,
                'options'     => $q->options->map(fn($o) => [
                    'id'      => $o->id,
                    'content' => $o->content,
                    // Ne pas exposer is_correct avant soumission
                ]),
            ]);
        }

        return response()->json($data);
    }

    /**
     * @OA\Post(
     *     path="/admin/exercises",
     *     summary="Créer un exercice",
     *     tags={"Admin - Exercices"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","competence_id","type","level"},
     *             @OA\Property(property="title",         type="string"),
     *             @OA\Property(property="description",   type="string"),
     *             @OA\Property(property="competence_id", type="string"),
     *             @OA\Property(property="type",          type="string", enum={"LESSON","PRACTICE","QCM"}),
     *             @OA\Property(property="level",         type="string", enum={"A1","A2","B1","B2","C1","C2"}),
     *             @OA\Property(property="content",       type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Exercice créé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'competence_id' => 'required|exists:competences,id',
            'type'          => 'required|in:LESSON,PRACTICE,QCM',
            'level'         => 'required|in:A1,A2,B1,B2,C1,C2',
            'content'       => 'nullable|string',
        ]);

        $exercise = Exercise::create($request->only([
            'title', 'description', 'competence_id', 'type', 'level', 'content'
        ]));

        return response()->json($exercise, 201);
    }
}
