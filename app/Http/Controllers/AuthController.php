<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use App\Models\Learner;
use App\Models\Progress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/register",
     *     summary="Inscription d'un nouvel apprenant",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name",                  type="string",  example="Aissatou Koné"),
     *             @OA\Property(property="email",                 type="string",  example="aissatou@email.com"),
     *             @OA\Property(property="password",              type="string",  example="password123"),
     *             @OA\Property(property="password_confirmation", type="string",  example="password123"),
     *             @OA\Property(property="phone",                 type="string",  example="+22507000000"),
     *             @OA\Property(property="country",               type="string",  example="Côte d'Ivoire"),
     *             @OA\Property(property="target_exam_date",      type="string",  format="date", example="2025-12-01"),
     *             @OA\Property(property="registration_type",     type="string",  enum={"SOLO","COACHED"}, example="SOLO")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string",  example="Inscription réussie"),
     *             @OA\Property(property="token",   type="string",  example="1|abc123..."),
     *             @OA\Property(property="user",    type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|string|email|unique:users',
            'password'          => 'required|string|min:8|confirmed',
            'phone'             => 'nullable|string|max:20',
            'country'           => 'nullable|string|max:100',
            'target_exam_date'  => 'nullable|date|after:today',
            'registration_type' => 'nullable|in:SOLO,COACHED',
        ]);

        $registrationType = $request->get('registration_type', 'SOLO');

        $user = DB::transaction(function () use ($request, $registrationType) {

            // 1. Créer l'utilisateur
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'phone'    => $request->phone,
                'role'     => $registrationType,
                'status'   => 'active',
            ]);

            // 2. Assigner le rôle Spatie
            $user->assignRole($registrationType);

            // 3. Créer le profil Learner
            Learner::create([
                'user_id'             => $user->id,
                'registration_type'   => $registrationType,
                'country'             => $request->country,
                'target_exam_date'    => $request->target_exam_date,
                'global_score'        => 0,
                'is_expert_candidate' => false,
                'last_active_at'      => now(),
            ]);

            // 4. Initialiser la progression à 0 pour les 4 compétences
            $competences = Competence::all();
            foreach ($competences as $competence) {
                Progress::create([
                    'learner_id'    => $user->id,
                    'competence_id' => $competence->id,
                    'score'         => 0,
                ]);
            }

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie',
            'token'   => $token,
            'user'    => $user->load(['roles', 'learner.progress.competence']),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Connexion",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email",    type="string", example="aissatou@email.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Connexion réussie"),
     *             @OA\Property(property="token",   type="string", example="1|abc123..."),
     *             @OA\Property(property="user",    type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Identifiants incorrects"),
     *     @OA\Response(response=403, description="Compte désactivé")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // Vérification identifiants
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        // Vérification statut compte
        if ($user->status === 'inactive') {
            return response()->json([
                'message' => 'Votre compte a été désactivé. Contactez l\'administrateur.',
            ], 403);
        }

        // Mise à jour last_login_at
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Charger le profil selon le rôle
        $profile = match($user->role) {
            'ADMIN'            => $user->load(['roles', 'admin']),
            'COACH'            => $user->load(['roles', 'coach']),
            'SOLO', 'COACHED'  => $user->load(['roles', 'learner.progress.competence']),
            default            => $user->load('roles'),
        };

        return response()->json([
            'message' => 'Connexion réussie',
            'token'   => $token,
            'user'    => $profile,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Déconnexion",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Déconnexion réussie"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     summary="Profil de l'utilisateur connecté",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profil retourné avec succès",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = match($user->role) {
            'ADMIN'            => $user->load(['roles', 'admin']),
            'COACH'            => $user->load(['roles', 'coach']),
            'SOLO', 'COACHED'  => $user->load(['roles', 'learner.progress.competence']),
            default            => $user->load('roles'),
        };

        return response()->json($profile);
    }
}
