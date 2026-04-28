<?php

namespace App\Policies;

use App\Enums\ReopenScope;
use App\Models\ReopenRequest;
use App\Models\Requirement;
use App\Models\User;

class ReopenRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Criar request exige papel efetivo coerente com o escopo:
     * - requirement_approval: só RA do projeto
     * - qa_approval: só QA do projeto
     */
    public function create(User $user, Requirement $requirement, ReopenScope $scope): bool
    {
        $project = $requirement->project;
        if (! $project) {
            return false;
        }

        return $project->effectiveRoleFor($user) === $scope->requesterRole();
    }

    public function view(User $user, ReopenRequest $request): bool
    {
        // Requester sempre pode acompanhar o próprio pedido.
        if ($request->requested_by === $user->id) {
            return true;
        }

        $project = $request->requirement?->project;
        if (! $project) {
            return false;
        }

        return $project->effectiveRoleFor($user) === $request->scope->deciderRole();
    }

    public function decide(User $user, ReopenRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $project = $request->requirement?->project;
        if (! $project) {
            return false;
        }

        return $project->effectiveRoleFor($user) === $request->scope->deciderRole();
    }
}
