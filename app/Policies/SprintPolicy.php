<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;

class SprintPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->isProjectManager($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->isProjectManager($user, $project);
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $this->isProjectManager($user, $sprint->project);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return $this->isProjectManager($user, $sprint->project);
    }

    private function isProjectManager(User $user, Project $project): bool
    {
        return $project->effectiveRoleFor($user) === 'project_manager';
    }
}
