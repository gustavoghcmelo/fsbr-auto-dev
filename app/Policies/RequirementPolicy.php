<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->isProjectRequirementAnalyst($user, $project);
    }

    public function view(User $user, Requirement $requirement): bool
    {
        return $this->isProjectRequirementAnalyst($user, $requirement->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->isProjectRequirementAnalyst($user, $project);
    }

    public function update(User $user, Requirement $requirement): bool
    {
        return $this->isProjectRequirementAnalyst($user, $requirement->project);
    }

    public function delete(User $user, Requirement $requirement): bool
    {
        return $this->isProjectRequirementAnalyst($user, $requirement->project);
    }

    /**
     * Quem marca o requisito como "pronto para desenvolvimento" é o QA
     * do projeto (ou admin via bypass no before()).
     */
    public function qaApprove(User $user, Requirement $requirement): bool
    {
        return $requirement->project->effectiveRoleFor($user) === 'quality_assurance';
    }

    private function isProjectRequirementAnalyst(User $user, Project $project): bool
    {
        return $project->effectiveRoleFor($user) === 'requirement_analyst';
    }
}
