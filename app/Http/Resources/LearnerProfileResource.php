<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearnerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->user_id,
            'name'                 => $this->user->name,
            'email'                => $this->user->email,
            'phone'                => $this->user->phone,
            'avatar_url'           => $this->user->avatar_url,
            'registration_type'    => $this->registration_type,
            'country'              => $this->country,
            'estimated_level'      => $this->estimated_level,
            'global_score'         => (float) $this->global_score,
            'is_expert_candidate'  => (bool) $this->is_expert_candidate,
            'target_exam_date'     => $this->target_exam_date?->toDateString(),
            'last_active_at'       => $this->last_active_at?->toIso8601String(),
            'is_inactive'          => $this->last_active_at
                                        ? $this->last_active_at->lt(now()->subDays(7))
                                        : true,
            'status'               => $this->user->status,
            'created_at'           => $this->created_at->toIso8601String(),
            'progress'             => $this->whenLoaded('progress', function () {
                return $this->progress->map(fn($p) => [
                    'competence_code'  => $p->competence->code,
                    'competence_name'  => $p->competence->name,
                    'weight'           => (float) $p->competence->weight,
                    'score'            => (float) $p->score,
                    'level'            => $p->level,
                ]);
            }),
            'last_coach_message'   => $this->whenLoaded('coachMessages', function () {
                $last = $this->coachMessages->sortByDesc('created_at')->first();
                return $last ? [
                    'id'         => $last->id,
                    'message'    => $last->message,
                    'is_pinned'  => $last->is_pinned,
                    'created_at' => $last->created_at->toIso8601String(),
                ] : null;
            }),
            'next_appointment'     => $this->whenLoaded('appointments', function () {
                $next = $this->appointments
                    ->where('status', 'SCHEDULED')
                    ->where('start_at', '>=', now())
                    ->sortBy('start_at')
                    ->first();
                return $next ? [
                    'id'           => $next->id,
                    'title'        => $next->title,
                    'start_at'     => $next->start_at->toIso8601String(),
                    'end_at'       => $next->end_at->toIso8601String(),
                    'mode'         => $next->mode,
                    'meeting_link' => $next->meeting_link,
                ] : null;
            }),
        ];
    }
}
