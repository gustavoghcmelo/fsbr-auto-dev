<?php

namespace App\Http\Controllers;

use App\Enums\TestPlanStatus;
use App\Http\Requests\StoreTestPlanRequest;
use App\Http\Requests\UpdateTestPlanRequest;
use App\Models\Project;
use App\Models\TestPlan;
use App\Services\Testing\TestCaseGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestPlanController extends Controller
{
    public function store(StoreTestPlanRequest $request, Project $project): RedirectResponse
    {
        $plan = $project->testPlans()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('projects.test-plans.show', [$project, $plan])
            ->with('status', 'Plano de testes criado.');
    }

    public function show(Request $request, Project $project, TestPlan $plan): Response
    {
        abort_unless($plan->project_id === $project->id, 404);
        $this->authorize('view', $plan);

        $plan->load([
            'creator:id,name',
            'cases' => fn ($q) => $q->orderBy('order'),
            'cases.requirement:id,title,status,approved_at,qa_approved_at,qa_approved_by',
            'cases.requirement.qaApprover:id,name',
            'cases.requirement.reopenRequests' => fn ($q) => $q->latest('id'),
            'cases.requirement.reopenRequests.requester:id,name',
            'cases.requirement.reopenRequests.decider:id,name',
            'cases.creator:id,name',
        ]);

        $approvedRequirements = $project->requirements()
            ->approved()
            ->orderBy('order')
            ->get(['id', 'title']);

        $coveredIds = $plan->cases->pluck('requirement_id')->filter()->unique()->values();
        $pendingRequirements = $approvedRequirements->reject(
            fn ($r) => $coveredIds->contains($r->id)
        )->values();

        return Inertia::render('Projects/TestPlans/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'plan' => $this->toDetail($plan),
            'requirements' => [
                'approved_total' => $approvedRequirements->count(),
                'pending_generation' => $pendingRequirements->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                ])->all(),
            ],
            'statuses' => $this->availableStatuses(),
        ]);
    }

    public function update(
        UpdateTestPlanRequest $request,
        Project $project,
        TestPlan $plan
    ): RedirectResponse {
        abort_unless($plan->project_id === $project->id, 404);

        $plan->update($request->validated());

        return back()->with('status', 'Plano atualizado.');
    }

    public function destroy(Project $project, TestPlan $plan): RedirectResponse
    {
        abort_unless($plan->project_id === $project->id, 404);
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Plano de testes removido.');
    }

    public function generateCases(
        Request $request,
        Project $project,
        TestPlan $plan,
        TestCaseGenerator $generator,
    ): RedirectResponse {
        abort_unless($plan->project_id === $project->id, 404);
        $this->authorize('update', $plan);

        $result = $generator->generateForPlan($plan, $request->user());

        $msg = $result['created'] > 0
            ? "{$result['created']} caso(s) gerado(s)."
            : 'Nenhum requisito aprovado pendente de geração.';

        return back()->with('status', $msg);
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(TestPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'status' => $plan->status->value,
            'status_label' => $plan->status->label(),
            'status_color' => $plan->status->color(),
            'created_by' => $plan->creator ? [
                'id' => $plan->creator->id,
                'name' => $plan->creator->name,
            ] : null,
            'created_at' => $plan->created_at?->toIso8601String(),
            'cases' => $plan->cases->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'preconditions' => $c->preconditions,
                'steps' => $c->steps ?? [],
                'expected_result' => $c->expected_result,
                'gherkin' => $c->gherkin,
                'priority' => $c->priority->value,
                'priority_label' => $c->priority->label(),
                'priority_color' => $c->priority->color(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'status_color' => $c->status->color(),
                'order' => $c->order,
                'requirement' => $c->requirement ? [
                    'id' => $c->requirement->id,
                    'title' => $c->requirement->title,
                    'analyst_approved' => $c->requirement->approved_at !== null,
                    'qa_approved' => $c->requirement->qa_approved_at !== null,
                    'qa_approved_at' => $c->requirement->qa_approved_at?->toIso8601String(),
                    'qa_approver' => $c->requirement->qaApprover ? [
                        'id' => $c->requirement->qaApprover->id,
                        'name' => $c->requirement->qaApprover->name,
                    ] : null,
                    'latest_reopen' => $this->buildLatestReopenMap($c->requirement),
                ] : null,
                'created_by' => $c->creator ? [
                    'id' => $c->creator->id,
                    'name' => $c->creator->name,
                ] : null,
            ])->values(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function availableStatuses(): array
    {
        return array_map(
            fn (TestPlanStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ],
            TestPlanStatus::cases(),
        );
    }

    /**
     * Mapa { scope => { ...payload } } da reabertura mais recente por escopo
     * (independente do status). A UI usa `status` para decidir o que exibir:
     * pendente, aprovada ou recusada com motivo.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildLatestReopenMap(\App\Models\Requirement $r): array
    {
        $map = [];
        foreach ($r->reopenRequests ?? [] as $rr) {
            $key = $rr->scope->value;
            if (isset($map[$key])) {
                continue;
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
}
