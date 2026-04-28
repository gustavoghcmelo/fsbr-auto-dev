<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestPlan;
use App\Models\User;

class TestPlanPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->isProjectQa($user, $project);
    }

    public function view(User $user, TestPlan $plan): bool
    {
        return $this->isProjectQa($user, $plan->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->isProjectQa($user, $project);
    }

    public function update(User $user, TestPlan $plan): bool
    {
        return $this->isProjectQa($user, $plan->project);
    }

    public function delete(User $user, TestPlan $plan): bool
    {
        return $this->isProjectQa($user, $plan->project);
    }

    private function isProjectQa(User $user, Project $project): bool
    {
        return $project->effectiveRoleFor($user) === 'quality_assurance';
    }
}
