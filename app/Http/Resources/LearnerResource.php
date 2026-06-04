<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearnerResource extends JsonResource
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
        ];
    }
}
