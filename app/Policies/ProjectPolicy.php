<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user) || $project->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->profile?->slug, ['admin', 'project_manager'], true);
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->owner_id === $user->id) {
            return true;
        }

        return $project->effectiveRoleFor($user) === 'project_manager';
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
