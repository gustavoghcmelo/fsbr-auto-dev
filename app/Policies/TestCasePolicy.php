<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;

class TestCasePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, TestCase $case): bool
    {
        return $this->isProjectQa($user, $case->plan->project);
    }

    public function create(User $user, TestPlan $plan): bool
    {
        return $this->isProjectQa($user, $plan->project);
    }

    public function update(User $user, TestCase $case): bool
    {
        return $this->isProjectQa($user, $case->plan->project);
    }

    public function delete(User $user, TestCase $case): bool
    {
        return $this->isProjectQa($user, $case->plan->project);
    }

    private function isProjectQa(User $user, Project $project): bool
    {
        return $project->effectiveRoleFor($user) === 'quality_assurance';
    }
}
