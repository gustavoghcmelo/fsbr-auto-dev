<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestCaseRequest;
use App\Http\Requests\UpdateTestCaseRequest;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestCaseController extends Controller
{
    public function store(
        StoreTestCaseRequest $request,
        Project $project,
        TestPlan $plan
    ): RedirectResponse {
        abort_unless($plan->project_id === $project->id, 404);

        $nextOrder = ($plan->cases()->max('order') ?? -1) + 1;

        $plan->cases()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'order' => $nextOrder,
        ]);

        return back()->with('status', 'Caso de teste criado.');
    }

    public function update(
        UpdateTestCaseRequest $request,
        Project $project,
        TestPlan $plan,
        TestCase $case,
    ): RedirectResponse {
        abort_unless($plan->project_id === $project->id, 404);
        abort_unless($case->test_plan_id === $plan->id, 404);

        $case->update($request->validated());

        return back()->with('status', 'Caso de teste atualizado.');
    }

    public function destroy(
        Request $request,
        Project $project,
        TestPlan $plan,
        TestCase $case,
    ): RedirectResponse {
        abort_unless($plan->project_id === $project->id, 404);
        abort_unless($case->test_plan_id === $plan->id, 404);
        $this->authorize('delete', $case);

        $case->delete();

        return back()->with('status', 'Caso de teste removido.');
    }
}
