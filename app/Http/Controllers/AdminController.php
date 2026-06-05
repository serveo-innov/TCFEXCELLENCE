<?php

namespace App\Http\Controllers;

use App\Http\Resources\LearnerResource;
use App\Http\Resources\LearnerProfileResource;
use App\Models\CoachMessage;
use App\Models\Learner;
use App\Services\ScoreCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    public function __construct(protected ScoreCalculatorService $scoreService)
    {
    }

    /**
     * @OA\Get(path="/admin/learners", summary="Liste paginée des apprenants", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"SOLO","COACHED"})),
     *     @OA\Parameter(name="country", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="level", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="inactive", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Liste retournée"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function learners(Request $request): AnonymousResourceCollection
    {
        $query = Learner::with(['user', 'progress.competence']);

        if ($request->filled('type'))     $query->where('registration_type', strtoupper($request->type));
        if ($request->filled('country'))  $query->where('country', $request->country);
        if ($request->filled('level'))    $query->where('estimated_level', strtoupper($request->level));
        if ($request->boolean('inactive')) {
            $query->where(function ($q) {
                $q->where('last_active_at', '<', now()->subDays(7))->orWhereNull('last_active_at');
            });
        }

        $query->orderByRaw('last_active_at IS NULL ASC')->orderBy('last_active_at', 'desc');

        return LearnerResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * @OA\Get(path="/admin/learners/kpis", summary="KPIs du tableau de bord", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="KPIs retournés")
     * )
     */
    public function kpis(): JsonResponse
    {
        $soloTotal  = Learner::where('registration_type', 'SOLO')->count();
        $soloActifs = Learner::where('registration_type', 'SOLO')->where('last_active_at', '>=', now()->subDays(7))->count();

        $coachedTotal    = Learner::where('registration_type', 'COACHED')->count();
        $coachedActifs   = Learner::where('registration_type', 'COACHED')->where('last_active_at', '>=', now()->subDays(7))->count();
        $tauxReussite    = Learner::where('registration_type', 'COACHED')->where('global_score', '>=', 75)->count();

        return response()->json([
            'solo'    => ['total' => $soloTotal, 'actifs' => $soloActifs, 'inactifs' => $soloTotal - $soloActifs],
            'coached' => [
                'total' => $coachedTotal, 'actifs' => $coachedActifs, 'sessions_semaine' => 0,
                'taux_reussite' => $coachedTotal > 0 ? round(($tauxReussite / $coachedTotal) * 100, 1) : 0,
            ],
        ]);
    }

    /**
     * @OA\Get(path="/admin/learners/{id}", summary="Profil complet d'un apprenant", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Profil retourné"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function learnerProfile(string $id): JsonResponse
    {
        $learner = Learner::with(['user','progress.competence','coachMessages','appointments'])
            ->where('user_id', $id)->firstOrFail();

        return response()->json([
            'learner' => new LearnerProfileResource($learner),
            'scores'  => $this->scoreService->getDetailedScores($id),
        ]);
    }

    /**
     * @OA\Get(path="/admin/learners/export", summary="Export CSV", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"SOLO","COACHED"})),
     *     @OA\Response(response=200, description="Fichier CSV")
     * )
     */
    public function exportCsv(Request $request)
    {
        $query = Learner::with(['user']);
        if ($request->filled('type')) $query->where('registration_type', strtoupper($request->type));
        $learners = $query->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="apprenants_'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($learners) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, ['ID','Nom','Email','Téléphone','Type','Pays','Niveau','Score','Expert','Date examen','Dernière activité','Inscrit le']);
            foreach ($learners as $l) {
                fputcsv($file, [
                    $l->user_id, $l->user->name, $l->user->email, $l->user->phone ?? '',
                    $l->registration_type, $l->country ?? '', $l->estimated_level ?? '',
                    $l->global_score, $l->is_expert_candidate ? 'Oui' : 'Non',
                    $l->target_exam_date?->toDateString() ?? '',
                    $l->last_active_at?->toDateTimeString() ?? '',
                    $l->created_at->toDateTimeString(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @OA\Post(path="/admin/learners/{id}/message", summary="Envoyer un message coach", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"message"},
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="is_pinned", type="boolean")
     *     )),
     *     @OA\Response(response=201, description="Message envoyé"),
     *     @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function sendCoachMessage(Request $request, string $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000', 'is_pinned' => 'boolean']);
        Learner::where('user_id', $id)->firstOrFail();

        $message = CoachMessage::create([
            'learner_id' => $id,
            'coach_id'   => $request->user()->id,
            'message'    => $request->message,
            'is_pinned'  => $request->boolean('is_pinned', false),
        ]);

        return response()->json(['message' => 'Message envoyé.', 'data' => $message], 201);
    }

    /**
     * @OA\Patch(path="/admin/learner/{id}/coach-message", summary="Modifier le dernier message coach", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"message"},
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="is_pinned", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Message modifié"),
     *     @OA\Response(response=404, description="Aucun message")
     * )
     */
    public function updateCoachMessage(Request $request, string $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000', 'is_pinned' => 'boolean']);

        $message = CoachMessage::where('learner_id', $id)->orderByDesc('created_at')->firstOrFail();
        $message->update([
            'message'   => $request->message,
            'is_pinned' => $request->boolean('is_pinned', $message->is_pinned),
        ]);

        return response()->json(['message' => 'Message modifié.', 'data' => $message]);
    }

    /**
     * @OA\Patch(path="/admin/learner/{id}/banner", summary="Activer/désactiver la bannière", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"show_banner"},
     *         @OA\Property(property="show_banner", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Bannière mise à jour")
     * )
     */
    public function toggleBanner(Request $request, string $id): JsonResponse
    {
        $request->validate(['show_banner' => 'required|boolean']);
        $learner = Learner::where('user_id', $id)->firstOrFail();
        $learner->update([
            'banner_hidden_until' => $request->boolean('show_banner') ? null : now()->addYears(10),
        ]);

        return response()->json(['message' => 'Bannière mise à jour.']);
    }

    /**
     * @OA\Post(path="/admin/learner/{id}/note-privee", summary="Ajouter une note privée", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"note"},
     *         @OA\Property(property="note", type="string")
     *     )),
     *     @OA\Response(response=200, description="Note enregistrée")
     * )
     */
    public function addPrivateNote(Request $request, string $id): JsonResponse
    {
        $request->validate(['note' => 'required|string|max:5000']);
        $learner = Learner::where('user_id', $id)->firstOrFail();
        $learner->update(['private_note' => $request->note]);

        return response()->json(['message' => 'Note privée enregistrée.']);
    }

    /**
     * @OA\Get(path="/admin/stats", summary="Statistiques globales", tags={"Admin"}, security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statistiques retournées")
     * )
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_learners' => Learner::count(),
            'total_solo'     => Learner::where('registration_type', 'SOLO')->count(),
            'total_coached'  => Learner::where('registration_type', 'COACHED')->count(),
            'total_experts'  => Learner::where('is_expert_candidate', true)->count(),
            'average_score'  => round(Learner::avg('global_score') ?? 0, 2),
            'inactive_count' => Learner::where(function ($q) {
                $q->where('last_active_at', '<', now()->subDays(7))->orWhereNull('last_active_at');
            })->count(),
        ]);
    }
}
