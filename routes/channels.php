<?php

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Facades\Broadcast;

// Canal presence pour un groupe de discussion
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    $group = Group::find($groupId);

    if (!$group) return false;

    $isCoach  = $group->coach_id === $user->id;
    $isAdmin  = $user->hasRole('ADMIN');
    $isMember = GroupMember::where('group_id', $groupId)
        ->where('learner_id', $user->id)
        ->where('is_active', true)
        ->exists();

    if (!$isCoach && !$isAdmin && !$isMember) return false;

    return [
        'id'   => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});
