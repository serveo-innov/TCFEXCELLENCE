<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearnerDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 3 dernières soumissions
        $lastSubmissions = $this->submissions()
            ->with('exercise.competence')
            ->latest('submitted_at')
            ->take(3)
            ->get()
            ->map(fn($s) => [
                'id'              => $s->id,
                'exercise_title'  => $s->exercise->title,
                'competence_code' => $s->exercise->competence->code,
                'score'           => (float) $s->score,
                'score_color'     => $s->score >= 75 ? 'green' : ($s->score >= 50 ? 'orange' : 'red'),
                'submitted_at'    => $s->submitted_at?->toIso8601String(),
                'status'          => $s->status,
            ]);

        // Dernier message coach (pinned en priorité)
        $lastMessage = $this->coachMessages()
            ->with('coach.user')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->first();

        // Prochain RDV
        $nextAppointment = $this->appointments()
            ->where('status', 'SCHEDULED')
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->first();

        // Progression par compétence
        $progress = $this->progress()->with('competence')
            ->get()
            ->sortByDesc(fn($p) => $p->competence->weight)
            ->values()
            ->map(fn($p) => [
                'competence_id'   => $p->competence_id,
                'competence_code' => $p->competence->code,
                'competence_name' => $p->competence->name,
                'weight'          => (float) $p->competence->weight,
                'score'           => (float) $p->score,
                'level'           => $p->level,
            ]);

        // Bannière visible ?
        $showBanner = $this->registration_type === 'SOLO'
            && $this->global_score >= 70
            && $this->last_active_at?->lt(now()->subWeeks(2)) === false
            && $this->last_active_at?->gte(now()->subWeeks(2))
            && (
                is_null($this->banner_hidden_until)
                || $this->banner_hidden_until->lt(now())
            );

        return [
            'id'                  => $this->user_id,
            'name'                => $this->user->name,
            'email'               => $this->user->email,
            'avatar_url'          => $this->user->avatar_url,
            'registration_type'   => $this->registration_type,
            'country'             => $this->country,
            'estimated_level'     => $this->estimated_level,
            'global_score'        => (float) $this->global_score,
            'is_expert_candidate' => (bool) $this->is_expert_candidate,
            'target_exam_date'    => $this->target_exam_date?->toDateString(),
            'last_active_at'      => $this->last_active_at?->toIso8601String(),
            'progress'            => $progress,
            'last_coach_message'  => $lastMessage ? [
                'id'         => $lastMessage->id,
                'message'    => $lastMessage->message,
                'is_pinned'  => $lastMessage->is_pinned,
                'coach_name' => $lastMessage->coach->user->name,
                'created_at' => $lastMessage->created_at->toIso8601String(),
            ] : null,
            'next_appointment' => $nextAppointment ? [
                'id'           => $nextAppointment->id,
                'title'        => $nextAppointment->title,
                'description'  => $nextAppointment->description,
                'start_at'     => $nextAppointment->start_at->toIso8601String(),
                'end_at'       => $nextAppointment->end_at->toIso8601String(),
                'mode'         => $nextAppointment->mode,
                'meeting_link' => $nextAppointment->meeting_link,
            ] : null,
            'last_submissions' => $lastSubmissions,
            'show_banner'      => $showBanner,
        ];
    }
}
