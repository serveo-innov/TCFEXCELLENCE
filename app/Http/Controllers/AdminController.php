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
     * @OA\Get(
     *     path="/admin/learners",
     *     summary="Liste paginée des apprenants",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type",        in="query", description="SOLO ou COACHED", @OA\Schema(type="string", enum={"SOLO","COACHED"})),
     *     @OA\Parameter(name="country",     in="query", description="Filtre par pays",  @OA\Schema(type="string")),
     *     @OA\Parameter(name="level",       in="query", description="Niveau estimé",    @OA\Schema(type="string", enum={"A1","A2","B1","B2","C1","C2"})),
     *     @OA\Parameter(name="inactive",    in="query", description="Inactifs > 7j",    @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page",    in="query", description="Items par page",   @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Liste retournée avec succès"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function learners(Request $request): AnonymousResourceCollection
    {
        $query = Learner::with(['user', 'progress.competence']);

        // Filtre par type SOLO / COACHED
        if ($request->filled('type')) {
            $query->where('registration_type', strtoupper($request->type));
        }

        // Filtre par pays
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        // Filtre par niveau estimé
        if ($request->filled('level')) {
            $query->where('estimated_level', strtoupper($request->level));
        }

        // Filtre inactifs (last_active_at > 7 jours)
        if ($request->boolean('inactive')) {
            $query->where(function ($q) {
                $q->where('last_active_at', '<', now()->subDays(7))
                  ->orWhereNull('last_active_at');
            });
        }

        // Tri : apprenants actifs en premier, ensuite par date de création desc
        $query->orderByRaw('last_active_at IS NULL ASC')
              ->orderBy('last_active_at', 'desc');

        return LearnerResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    /**
     * @OA\Get(
     *     path="/admin/learners/kpis",
     *     summary="KPIs du tableau de bord admin",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="KPIs retournés")
     * )
     */
    public function kpis(): JsonResponse
    {
        $soloTotal    = Learner::where('registration_type', 'SOLO')->count();
        $soloActifs   = Learner::where('registration_type', 'SOLO')
                               ->where('last_active_at', '>=', now()->subDays(7))->count();
        $soloInactifs = $soloTotal - $soloActifs;

        $coachedTotal    = Learner::where('registration_type', 'COACHED')->count();
        $coachedActifs   = Learner::where('registration_type', 'COACHED')
                                  ->where('last_active_at', '>=', now()->subDays(7))->count();
        $sessionsSemaine = 0; // À brancher sur Appointment quand le module est prêt
        $tauxReussite    = Learner::where('registration_type', 'COACHED')
                                  ->where('global_score', '>=', 75)->count();

        return response()->json([
            'solo' => [
                'total'    => $soloTotal,
                'actifs'   => $soloActifs,
                'inactifs' => $soloInactifs,
            ],
            'coached' => [
                'total'           => $coachedTotal,
                'actifs'          => $coachedActifs,
                'sessions_semaine'=> $sessionsSemaine,
                'taux_reussite'   => $coachedTotal > 0
                    ? round(($tauxReussite / $coachedTotal) * 100, 1)
                    : 0,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/learners/{id}",
     *     summary="Profil complet d'un apprenant (drawer)",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Profil retourné"),
     *     @OA\Response(response=404, description="Apprenant non trouvé")
     * )
     */
    public function learnerProfile(string $id): JsonResponse
    {
        $learner = Learner::with([
            'user',
            'progress.competence',
            'coachMessages',
            'appointments',
        ])->where('user_id', $id)->firstOrFail();

        $scores = $this->scoreService->getDetailedScores($id);

        return response()->json([
            'learner' => new LearnerProfileResource($learner),
            'scores'  => $scores,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/learners/export",
     *     summary="Export CSV des apprenants",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"SOLO","COACHED"})),
     *     @OA\Response(response=200, description="Fichier CSV téléchargeable")
     * )
     */
    public function exportCsv(Request $request)
    {
        $query = Learner::with(['user']);

        if ($request->filled('type')) {
            $query->where('registration_type', strtoupper($request->type));
        }

        $learners = $query->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="apprenants_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($learners) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID', 'Nom', 'Email', 'Téléphone', 'Type',
                'Pays', 'Niveau estimé', 'Score global',
                'Expert', 'Date examen cible', 'Dernière activité', 'Inscrit le',
            ]);

            foreach ($learners as $learner) {
                fputcsv($file, [
                    $learner->user_id,
                    $learner->user->name,
                    $learner->user->email,
                    $learner->user->phone ?? '',
                    $learner->registration_type,
                    $learner->country ?? '',
                    $learner->estimated_level ?? '',
                    $learner->global_score,
                    $learner->is_expert_candidate ? 'Oui' : 'Non',
                    $learner->target_exam_date?->toDateString() ?? '',
                    $learner->last_active_at?->toDateTimeString() ?? '',
                    $learner->created_at->toDateTimeString(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @OA\Post(
     *     path="/admin/learners/{id}/message",
     *     summary="Envoyer un message coach à un apprenant",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message",   type="string", example="Bravo pour vos progrès cette semaine !"),
     *             @OA\Property(property="is_pinned", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Message envoyé"),
     *     @OA\Response(response=404, description="Apprenant non trouvé")
     * )
     */
    public function sendCoachMessage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'message'   => 'required|string|max:2000',
            'is_pinned' => 'boolean',
        ]);

        // Vérifier que l'apprenant existe
        $learner = Learner::where('user_id', $id)->firstOrFail();

        $message = CoachMessage::create([
            'learner_id' => $id,
            'coach_id'   => $request->user()->id,
            'message'    => $request->message,
            'is_pinned'  => $request->boolean('is_pinned', false),
        ]);

        return response()->json([
            'message' => 'Message envoyé avec succès.',
            'data'    => $message,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/stats",
     *     summary="Statistiques globales",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statistiques retournées")
     * )
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_learners'  => Learner::count(),
            'total_solo'      => Learner::where('registration_type', 'SOLO')->count(),
            'total_coached'   => Learner::where('registration_type', 'COACHED')->count(),
            'total_experts'   => Learner::where('is_expert_candidate', true)->count(),
            'average_score'   => round(Learner::avg('global_score') ?? 0, 2),
            'inactive_count'  => Learner::where(function ($q) {
                $q->where('last_active_at', '<', now()->subDays(7))
                  ->orWhereNull('last_active_at');
            })->count(),
        ]);
    }
}
