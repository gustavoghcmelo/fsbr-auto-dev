<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::query()
            ->with('owner:id,name', 'users:id,name')
            ->when(! $user->isAdmin(), fn ($q) => $q->where(function ($inner) use ($user) {
                $inner->whereHas('users', fn ($rel) => $rel->whereKey($user->id))
                    ->orWhere('owner_id', $user->id);
            }))
            ->latest('id')
            ->get()
            ->map(fn (Project $p) => $this->toListItem($p));

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'can' => [
                'create' => $user->can('create', Project::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projects/Create', [
            'users' => $this->availableUsers(),
            'profiles' => $this->availableProfiles(),
            'statuses' => $this->availableStatuses(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $members = $data['members'] ?? [];
        unset($data['members']);

        $project = DB::transaction(function () use ($data, $members, $request) {
            $project = Project::create([
                ...$data,
                'owner_id' => $request->user()->id,
            ]);

            $project->users()->sync(
                $this->membersSyncMap($members, $project->owner_id)
            );

            return $project;
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Projeto criado com sucesso.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'owner:id,name,email',
            'users:id,name,email,profile_id',
            'users.profile:id,slug,name',
            'requirementDocuments' => fn ($q) => $q->latest('id'),
            'requirementDocuments.uploader:id,name',
            'requirements' => fn ($q) => $q->orderBy('order'),
            'requirements.document:id,original_filename',
            'requirements.approver:id,name',
            'requirements.qaApprover:id,name',
            'requirements.reopenRequests' => fn ($q) => $q->latest('id'),
            'requirements.reopenRequests.requester:id,name',
            'requirements.reopenRequests.decider:id,name',
            'testPlans' => fn ($q) => $q->latest('id')->withCount('cases'),
            'testPlans.creator:id,name',
        ]);

        $user = $request->user();
        $effectiveRole = $project->effectiveRoleFor($user);
        $canSeeBacklog = $user->isAdmin() || $effectiveRole === 'project_manager';
        $canSeeApprovals = $user->isAdmin()
            || in_array($effectiveRole, ['quality_assurance', 'project_manager'], true);

        return Inertia::render('Projects/Show', [
            'project' => $this->toDetail($project),
            'can' => [
                'update' => $user->can('update', $project),
                'manageRequirements' => $user->can('viewAny', [\App\Models\Requirement::class, $project]),
                'manageTestPlans' => $user->can('viewAny', [\App\Models\TestPlan::class, $project]),
                'viewBacklog' => $canSeeBacklog,
                'viewApprovals' => $canSeeApprovals,
            ],
            'backlog' => $canSeeBacklog ? $this->backlogFor($project) : [],
            'sprints' => $canSeeBacklog ? $this->sprintsFor($project) : [],
            'pending_reopen_requests' => $canSeeApprovals
                ? $this->pendingReopenFor($project, $user, $effectiveRole)
                : [],
        ]);
    }

    /**
     * Lista de solicitações de reabertura que o usuário tem prerrogativa
     * de decidir no projeto atual.
     *
     * @return list<array<string, mixed>>
     */
    private function pendingReopenFor(
        Project $project,
        User $user,
        ?string $effectiveRole,
    ): array {
        $scopes = [];
        if ($user->isAdmin()) {
            $scopes = ['requirement_approval', 'qa_approval'];
        } elseif ($effectiveRole === 'quality_assurance') {
            $scopes = ['requirement_approval'];
        } elseif ($effectiveRole === 'project_manager') {
            $scopes = ['qa_approval'];
        }

        if ($scopes === []) {
            return [];
        }

        return \App\Models\ReopenRequest::query()
            ->pending()
            ->whereIn('scope', $scopes)
            ->whereHas('requirement', fn ($q) => $q->where('project_id', $project->id))
            ->with(['requester:id,name', 'requirement:id,title'])
            ->latest('id')
            ->get()
            ->map(fn (\App\Models\ReopenRequest $r) => [
                'id' => $r->id,
                'scope' => $r->scope->value,
                'scope_label' => $r->scope->label(),
                'reason' => $r->reason,
                'requested_by' => $r->requester ? [
                    'id' => $r->requester->id,
                    'name' => $r->requester->name,
                ] : null,
                'requested_at' => $r->created_at?->toIso8601String(),
                'requirement' => [
                    'id' => $r->requirement->id,
                    'title' => $r->requirement->title,
                ],
            ])
            ->all();
    }

    /**
     * Backlog = requisitos prontos para dev E que ainda não foram
     * alocados em nenhuma sprint.
     *
     * @return list<array<string, mixed>>
     */
    private function backlogFor(Project $project): array
    {
        return $project->requirements()
            ->readyForDev()
            ->whereDoesntHave('sprints')
            ->with(['approver:id,name', 'qaApprover:id,name'])
            ->orderBy('order')
            ->get()
            ->map(fn (\App\Models\Requirement $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'context' => $r->context,
                'acceptance_criteria' => $r->acceptance_criteria,
                'gherkin' => $r->gherkin,
                'analyst_approver' => $r->approver ? [
                    'id' => $r->approver->id,
                    'name' => $r->approver->name,
                ] : null,
                'analyst_approved_at' => $r->approved_at?->toIso8601String(),
                'qa_approver' => $r->qaApprover ? [
                    'id' => $r->qaApprover->id,
                    'name' => $r->qaApprover->name,
                ] : null,
                'qa_approved_at' => $r->qa_approved_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sprintsFor(Project $project): array
    {
        return $project->sprints()
            ->with([
                'creator:id,name',
                'requirements:id,title,description,context,acceptance_criteria,gherkin,project_id,approved_at,approved_by,qa_approved_at,qa_approved_by',
                'requirements.approver:id,name',
                'requirements.qaApprover:id,name',
            ])
            ->orderBy('number')
            ->get()
            ->map(fn (\App\Models\Sprint $s) => [
                'id' => $s->id,
                'number' => $s->number,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
                'created_by' => $s->creator ? [
                    'id' => $s->creator->id,
                    'name' => $s->creator->name,
                ] : null,
                'requirements' => $s->requirements->map(fn (\App\Models\Requirement $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'context' => $r->context,
                    'acceptance_criteria' => $r->acceptance_criteria,
                    'gherkin' => $r->gherkin,
                    'analyst_approver' => $r->approver ? [
                        'id' => $r->approver->id,
                        'name' => $r->approver->name,
                    ] : null,
                    'analyst_approved_at' => $r->approved_at?->toIso8601String(),
                    'qa_approver' => $r->qaApprover ? [
                        'id' => $r->qaApprover->id,
                        'name' => $r->qaApprover->name,
                    ] : null,
                    'qa_approved_at' => $r->qa_approved_at?->toIso8601String(),
                ])->values(),
            ])
            ->all();
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        $project->load('users');

        return Inertia::render('Projects/Edit', [
            'project' => [
                ...$this->toListItem($project),
                'description' => $project->description,
                'github_repo_url' => $project->github_repo_url,
                'start_date' => $project->start_date?->toDateString(),
                'delivery_date' => $project->delivery_date?->toDateString(),
                'forecast_hours' => $project->forecast_hours,
                'members' => $project->users->map(fn (User $u) => [
                    'user_id' => $u->id,
                    'role_override' => $u->pivot->role_override,
                ])->values(),
            ],
            'users' => $this->availableUsers(),
            'profiles' => $this->availableProfiles(),
            'statuses' => $this->availableStatuses(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $members = $data['members'] ?? [];
        unset($data['members']);

        DB::transaction(function () use ($project, $data, $members) {
            $project->update($data);

            $project->users()->sync(
                $this->membersSyncMap($members, $project->owner_id)
            );
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Projeto atualizado.');
    }

    /**
     * Retorna um mapa { scope => { ...payload } } com a solicitação de
     * reabertura mais recente por escopo, incluindo status e motivo da decisão
     * (se já decidida). Permite à UI mostrar:
     *   - banner amarelo quando status = pending
     *   - banner vermelho com motivo quando status = denied
     *
     * @return array<string, array<string, mixed>>
     */
    private function latestReopenMapFor(\App\Models\Requirement $r): array
    {
        $map = [];
        foreach ($r->reopenRequests ?? [] as $rr) {
            $key = $rr->scope->value;
            if (isset($map[$key])) {
                continue; // já gravei o mais recente (relação vem ordenada DESC)
            }
            $map[$key] = [
                'id' => $rr->id,
                'status' => $rr->status->value,
                'status_label' => $rr->status->label(),
                'status_color' => $rr->status->color(),
                'reason' => $rr->reason,
                'decision_reason' => $rr->decision_reason,
                'requested_by' => $rr->requester ? [
                    'id' => $rr->requester->id,
                    'name' => $rr->requester->name,
                ] : null,
                'requested_at' => $rr->created_at?->toIso8601String(),
                'decided_by' => $rr->decider ? [
                    'id' => $rr->decider->id,
                    'name' => $rr->decider->name,
                ] : null,
                'decided_at' => $rr->decided_at?->toIso8601String(),
            ];
        }

        return $map;
    }

    /**
     * Garante que o owner esteja sempre no pivot, mesmo que o form
     * não tenha listado ele entre os membros. Se o usuário enviou um
     * `role_override` explícito pro owner, esse valor é preservado.
     *
     * @param  array<int, array{user_id: int, role_override?: ?string}>  $members
     * @return array<int, array{role_override: ?string}>
     */
    private function membersSyncMap(array $members, int $ownerId): array
    {
        $map = collect($members)
            ->mapWithKeys(fn ($m) => [
                $m['user_id'] => ['role_override' => $m['role_override'] ?? null],
            ])
            ->all();

        if (! array_key_exists($ownerId, $map)) {
            $map[$ownerId] = ['role_override' => null];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'status_color' => $p->status->color(),
            'start_date' => $p->start_date?->toDateString(),
            'delivery_date' => $p->delivery_date?->toDateString(),
            'owner' => $p->owner ? ['id' => $p->owner->id, 'name' => $p->owner->name] : null,
            'members_count' => $p->users->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'github_repo_url' => $p->github_repo_url,
            'start_date' => $p->start_date?->toDateString(),
            'delivery_date' => $p->delivery_date?->toDateString(),
            'forecast_hours' => $p->forecast_hours,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'status_color' => $p->status->color(),
            'owner' => $p->owner ? [
                'id' => $p->owner->id,
                'name' => $p->owner->name,
                'email' => $p->owner->email,
            ] : null,
            'members' => $p->users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'profile' => $u->profile ? [
                    'slug' => $u->profile->slug,
                    'name' => $u->profile->name,
                ] : null,
                'role_override' => $u->pivot->role_override,
                'effective_role' => $u->pivot->role_override ?: $u->profile?->slug,
            ])->values(),
            'documents' => $p->requirementDocuments->map(fn ($d) => [
                'id' => $d->id,
                'filename' => $d->original_filename,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'status_color' => $d->status->color(),
                'failure_reason' => $d->failure_reason,
                'uploaded_by' => $d->uploader ? ['id' => $d->uploader->id, 'name' => $d->uploader->name] : null,
                'created_at' => $d->created_at?->toIso8601String(),
            ])->values(),
            'test_plans' => $p->testPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'status' => $plan->status->value,
                'status_label' => $plan->status->label(),
                'status_color' => $plan->status->color(),
                'cases_count' => $plan->cases_count ?? 0,
                'created_by' => $plan->creator ? [
                    'id' => $plan->creator->id,
                    'name' => $plan->creator->name,
                ] : null,
                'created_at' => $plan->created_at?->toIso8601String(),
            ])->values(),
            'requirements' => $p->requirements->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'context' => $r->context,
                'acceptance_criteria' => $r->acceptance_criteria,
                'gherkin' => $r->gherkin,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_color' => $r->status->color(),
                'order' => $r->order,
                'approved_at' => $r->approved_at?->toIso8601String(),
                'approved_by' => $r->approver ? [
                    'id' => $r->approver->id,
                    'name' => $r->approver->name,
                ] : null,
                'qa_approved_at' => $r->qa_approved_at?->toIso8601String(),
                'qa_approved_by' => $r->qaApprover ? [
                    'id' => $r->qaApprover->id,
                    'name' => $r->qaApprover->name,
                ] : null,
                'latest_reopen' => $this->latestReopenMapFor($r),
                'document' => $r->document ? [
                    'id' => $r->document->id,
                    'filename' => $r->document->original_filename,
                ] : null,
            ])->values(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableUsers(): array
    {
        return User::query()
            ->with('profile:id,slug,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'profile_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'profile' => $u->profile ? [
                    'slug' => $u->profile->slug,
                    'name' => $u->profile->name,
                ] : null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function availableProfiles(): array
    {
        return Profile::query()
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Profile $p) => ['slug' => $p->slug, 'name' => $p->name])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function availableStatuses(): array
    {
        return array_map(
            fn (ProjectStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ],
            ProjectStatus::cases()
        );
    }
}
