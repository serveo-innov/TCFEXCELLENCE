<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GroupController extends Controller
{
    /**
     * @OA\Get(
     *     path="/groups",
     *     summary="Liste des groupes accessibles à l'utilisateur connecté",
     *     tags={"Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Groupes retournés")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $groups = Group::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->whereHas('members', fn($m) => $m->where('learner_id', $userId)->where('is_active', true))
                  ->orWhere('coach_id', $userId);
            })
            ->with(['coach.user', 'members'])
            ->get()
            ->map(fn($g) => [
                'id'          => $g->id,
                'name'        => $g->name,
                'type'        => $g->type,
                'competence'  => $g->competence,
                'coach_name'  => $g->coach->user->name,
                'member_count'=> $g->members->count(),
                'unread_count'=> 0, // À implémenter avec une table de lecture
            ]);

        return response()->json($groups);
    }

    /**
     * @OA\Get(
     *     path="/groups/{id}/messages",
     *     summary="Messages d'un groupe (pagination infinie)",
     *     tags={"Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="before", in="query", description="ID du message avant lequel charger", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Messages retournés"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        $this->checkAccess($request, $id);

        $query = Message::where('group_id', $id)
            ->where('is_hidden', false)
            ->with(['sender', 'attachments', 'replyTo.sender'])
            ->orderByDesc('created_at');

        if ($request->filled('before')) {
            $before = Message::find($request->before);
            if ($before) {
                $query->where('created_at', '<', $before->created_at);
            }
        }

        $messages = $query->take(50)->get()->reverse()->values();

        return response()->json([
            'data'     => $messages->map(fn($m) => $this->formatMessage($m)),
            'has_more' => $query->count() > 0,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/groups/{id}/messages",
     *     summary="Envoyer un message dans un groupe",
     *     tags={"Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="content",      type="string"),
     *             @OA\Property(property="message_type", type="string", enum={"TEXT","FILE","AUDIO"}),
     *             @OA\Property(property="reply_to_id",  type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Message envoyé"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $this->checkAccess($request, $id);

        $request->validate([
            'content'      => 'required_without:file|string|max:5000',
            'message_type' => 'in:TEXT,FILE,AUDIO',
            'reply_to_id'  => 'nullable|exists:messages,id',
            'file'         => 'nullable|file|max:10240',
        ]);

        $fileUrl  = null;
        $fileName = null;
        $fileType = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $path     = $file->store('group-files/' . $id, 's3');
            $fileUrl  = Storage::url($path);
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getMimeType();
            $fileSize = $file->getSize();
        }

        $message = Message::create([
            'group_id'     => $id,
            'sender_id'    => $request->user()->id,
            'content'      => $request->content,
            'message_type' => $request->get('message_type', 'TEXT'),
            'file_url'     => $fileUrl,
            'reply_to_id'  => $request->reply_to_id,
            'is_pinned'    => false,
            'is_hidden'    => false,
        ]);

        if ($fileUrl) {
            MessageAttachment::create([
                'message_id' => $message->id,
                'file_url'   => $fileUrl,
                'file_name'  => $fileName,
                'file_type'  => $fileType,
                'file_size'  => $fileSize,
            ]);
        }

        $message->load(['sender', 'attachments', 'replyTo.sender']);

        // Diffuser via Reverb
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($this->formatMessage($message), 201);
    }

    /**
     * @OA\Patch(
     *     path="/groups/{groupId}/messages/{messageId}/pin",
     *     summary="Épingler ou désépingler un message (coach uniquement)",
     *     tags={"Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="groupId",   in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Message épinglé/désépinglé")
     * )
     */
    public function pinMessage(Request $request, string $groupId, string $messageId): JsonResponse
    {
        $group = Group::findOrFail($groupId);

        if ($group->coach_id !== $request->user()->id && !$request->user()->hasRole('ADMIN')) {
            return response()->json(['message' => 'Seul le coach peut épingler un message.'], 403);
        }

        $message = Message::where('group_id', $groupId)->findOrFail($messageId);
        $message->update(['is_pinned' => !$message->is_pinned]);

        return response()->json([
            'message'   => $message->is_pinned ? 'Message épinglé.' : 'Message désépinglé.',
            'is_pinned' => $message->is_pinned,
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/groups/{groupId}/messages/{messageId}/hide",
     *     summary="Masquer un message silencieusement (coach uniquement)",
     *     tags={"Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="groupId",   in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Message masqué")
     * )
     */
    public function hideMessage(Request $request, string $groupId, string $messageId): JsonResponse
    {
        $group = Group::findOrFail($groupId);

        if ($group->coach_id !== $request->user()->id && !$request->user()->hasRole('ADMIN')) {
            return response()->json(['message' => 'Seul le coach peut masquer un message.'], 403);
        }

        $message = Message::where('group_id', $groupId)->findOrFail($messageId);
        $message->update(['is_hidden' => true]);

        return response()->json(['message' => 'Message masqué.']);
    }

    /**
     * @OA\Post(
     *     path="/groups/{groupId}/messages/{messageId}/react",
     *     summary="Réagir à un message avec un emoji",
     *     tags={"Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="groupId",   in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"emoji"},
     *         @OA\Property(property="emoji", type="string", example="👍")
     *     )),
     *     @OA\Response(response=200, description="Réaction enregistrée")
     * )
     */
    public function reactToMessage(Request $request, string $groupId, string $messageId): JsonResponse
    {
        $request->validate(['emoji' => 'required|string|max:10']);

        $this->checkAccess($request, $groupId);

        $message = Message::where('group_id', $groupId)->findOrFail($messageId);

        $existing = $message->reactions()
            ->where('user_id', $request->user()->id)
            ->where('emoji', $request->emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            $message->reactions()->create([
                'user_id' => $request->user()->id,
                'emoji'   => $request->emoji,
            ]);
            $action = 'added';
        }

        return response()->json([
            'action'   => $action,
            'reactions'=> $message->reactions()->with('user')->get()->groupBy('emoji')->map(fn($r) => $r->count()),
        ]);
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────

    private function checkAccess(Request $request, string $groupId): void
    {
        $userId = $request->user()->id;
        $group  = Group::findOrFail($groupId);

        $isMember = GroupMember::where('group_id', $groupId)
            ->where('learner_id', $userId)
            ->where('is_active', true)
            ->exists();

        $isCoach = $group->coach_id === $userId;
        $isAdmin = $request->user()->hasRole('ADMIN');

        if (!$isMember && !$isCoach && !$isAdmin) {
            abort(403, 'Accès refusé à ce groupe.');
        }
    }

    private function formatMessage(Message $m): array
    {
        return [
            'id'           => $m->id,
            'group_id'     => $m->group_id,
            'sender_id'    => $m->sender_id,
            'sender_name'  => $m->sender->name,
            'sender_role'  => $m->sender->role,
            'content'      => $m->content,
            'message_type' => $m->message_type,
            'file_url'     => $m->file_url,
            'is_pinned'    => $m->is_pinned,
            'reply_to'     => $m->replyTo ? [
                'id'          => $m->replyTo->id,
                'sender_name' => $m->replyTo->sender->name,
                'content'     => $m->replyTo->content,
            ] : null,
            'attachments'  => $m->attachments->map(fn($a) => [
                'id'        => $a->id,
                'file_url'  => $a->file_url,
                'file_name' => $a->file_name,
                'file_type' => $a->file_type,
                'file_size' => $a->file_size,
            ]),
            'created_at'   => $m->created_at->toIso8601String(),
        ];
    }
}
