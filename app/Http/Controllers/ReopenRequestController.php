<?php

namespace App\Http\Controllers;

use App\Enums\ReopenScope;
use App\Enums\ReopenStatus;
use App\Events\ReopenRequestCreated;
use App\Events\ReopenRequestDecided;
use App\Http\Requests\DecideReopenRequestRequest;
use App\Http\Requests\StoreReopenRequestRequest;
use App\Models\Project;
use App\Models\ReopenRequest;
use App\Models\Requirement;
use App\Models\User;
use App\Notifications\ReopenRequestResolved;
use App\Notifications\ReopenRequestSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReopenRequestController extends Controller
{
    public function store(
        StoreReopenRequestRequest $request,
        Project $project,
        Requirement $requirement,
    ): RedirectResponse {
        abort_unless($requirement->project_id === $project->id, 404);

        $scope = ReopenScope::from($request->validated('scope'));

        $this->ensureScopeIsApplicable($requirement, $scope);
        $this->ensureNoDuplicatePending($requirement, $scope);

        $reopen = $requirement->reopenRequests()->create([
            'scope' => $scope,
            'status' => ReopenStatus::Pending,
            'requested_by' => $request->user()->id,
            'reason' => $request->validated('reason'),
        ]);

        ReopenRequestCreated::dispatch($reopen);

        // Notifica por e-mail todos os usuários do projeto com papel efetivo
        // equivalente ao decisor daquele escopo.
        $recipients = $this->projectUsersWithRole($project, $scope->deciderRole());
        Notification::send($recipients, new ReopenRequestSubmitted($reopen));

        return back()->with('status', 'Solicitação de reabertura enviada.');
    }

    public function show(
        Request $request,
        Project $project,
        ReopenRequest $reopenRequest,
    ): Response {
        // Eager-load tudo o que policy e payload precisam — evita
        // lazy-load silencioso e garante que `requirement.project`
        // esteja populado antes do authorize.
        $reopenRequest->load([
            'requirement.project',
            'requester:id,name,email',
            'decider:id,name,email',
        ]);

        abort_unless($reopenRequest->requirement?->project_id === $project->id, 404);
        $this->authorize('view', $reopenRequest);

        $user = $request->user();
        $canDecide = $user->can('decide', $reopenRequest);

        return Inertia::render('Projects/ReopenRequests/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'request' => [
                'id' => $reopenRequest->id,
                'scope' => $reopenRequest->scope->value,
                'scope_label' => $reopenRequest->scope->label(),
                'status' => $reopenRequest->status->value,
                'status_label' => $reopenRequest->status->label(),
                'status_color' => $reopenRequest->status->color(),
                'reason' => $reopenRequest->reason,
                'requested_by' => $reopenRequest->requester ? [
                    'id' => $reopenRequest->requester->id,
                    'name' => $reopenRequest->requester->name,
                    'email' => $reopenRequest->requester->email,
                ] : null,
                'created_at' => $reopenRequest->created_at?->toIso8601String(),
                'decided_by' => $reopenRequest->decider ? [
                    'id' => $reopenRequest->decider->id,
                    'name' => $reopenRequest->decider->name,
                ] : null,
                'decided_at' => $reopenRequest->decided_at?->toIso8601String(),
                'decision_reason' => $reopenRequest->decision_reason,
                'requirement' => [
                    'id' => $reopenRequest->requirement->id,
                    'title' => $reopenRequest->requirement->title,
                    'description' => $reopenRequest->requirement->description,
                    'context' => $reopenRequest->requirement->context,
                    'acceptance_criteria' => $reopenRequest->requirement->acceptance_criteria,
                    'gherkin' => $reopenRequest->requirement->gherkin,
                ],
            ],
            'can' => [
                'decide' => $canDecide,
            ],
        ]);
    }

    public function decide(
        DecideReopenRequestRequest $request,
        Project $project,
        ReopenRequest $reopenRequest,
    ): RedirectResponse {
        abort_unless($reopenRequest->requirement->project_id === $project->id, 404);

        $data = $request->validated();
        $approved = $data['decision'] === 'approved';

        DB::transaction(function () use ($reopenRequest, $request, $data, $approved) {
            $reopenRequest->status = $approved
                ? ReopenStatus::Approved
                : ReopenStatus::Denied;
            $reopenRequest->decided_by = $request->user()->id;
            $reopenRequest->decided_at = now();
            $reopenRequest->decision_reason = $data['decision_reason'] ?? null;
            $reopenRequest->save();

            if ($approved) {
                $this->applyUnlock($reopenRequest);
            }
        });

        ReopenRequestDecided::dispatch($reopenRequest);

        $reopenRequest->loadMissing('requester');
        if ($reopenRequest->requester) {
            $reopenRequest->requester->notify(new ReopenRequestResolved($reopenRequest));
        }

        return back()->with(
            'status',
            $approved ? 'Solicitação aprovada.' : 'Solicitação recusada.'
        );
    }

    private function ensureScopeIsApplicable(Requirement $requirement, ReopenScope $scope): void
    {
        $applicable = match ($scope) {
            ReopenScope::RequirementApproval => $requirement->isApproved(),
            ReopenScope::QaApproval => $requirement->isQaApproved(),
        };

        if (! $applicable) {
            throw ValidationException::withMessages([
                'scope' => 'Este escopo de reabertura não está aplicável ao requisito no momento.',
            ]);
        }
    }

    private function ensureNoDuplicatePending(Requirement $requirement, ReopenScope $scope): void
    {
        $exists = $requirement->reopenRequests()
            ->where('scope', $scope->value)
            ->pending()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'scope' => 'Já existe uma solicitação pendente para este escopo.',
            ]);
        }
    }

    private function applyUnlock(ReopenRequest $reopen): void
    {
        $requirement = $reopen->requirement;

        if ($reopen->scope === ReopenScope::RequirementApproval) {
            $requirement->status = \App\Enums\RequirementStatus::Draft;
            $requirement->approved_at = null;
            $requirement->approved_by = null;
            // Cascata: QA approval se torna inválida quando o requisito é reaberto.
            $requirement->qa_approved_at = null;
            $requirement->qa_approved_by = null;
            $requirement->save();

            return;
        }

        if ($reopen->scope === ReopenScope::QaApproval) {
            $requirement->qa_approved_at = null;
            $requirement->qa_approved_by = null;
            $requirement->save();
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function projectUsersWithRole(Project $project, string $role): \Illuminate\Support\Collection
    {
        $project->loadMissing('users.profile');

        $members = $project->users->filter(
            fn (User $u) => ($u->pivot->role_override ?: $u->profile?->slug) === $role
        );

        // Inclui o owner se ele tem o papel equivalente e não está no pivot.
        if ($project->owner_id && ! $members->contains('id', $project->owner_id)) {
            $owner = User::query()->find($project->owner_id);
            if ($owner && $owner->profile?->slug === $role) {
                $members->push($owner);
            }
        }

        return $members->values();
    }
}
