<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Canal privado de projeto.
 * Só recebem eventos: o admin, o owner ou quem está na equipe do projeto.
 */
Broadcast::channel('projects.{projectId}', function (User $user, int $projectId) {
    $project = Project::find($projectId);

    if (! $project) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    return $project->owner_id === $user->id || $project->hasMember($user);
});
