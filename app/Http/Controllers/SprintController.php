<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSprintRequest;
use App\Http\Requests\UpdateSprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SprintController extends Controller
{
    public function store(StoreSprintRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $requirementIds = $data['requirement_ids'] ?? [];

        // Protege contra requisitos de outro projeto ou fora do "ready for dev"
        $validIds = $project->requirements()
            ->readyForDev()
            ->whereIn('id', $requirementIds)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($project, $data, $validIds, $request) {
            $nextNumber = ($project->sprints()->withTrashed()->max('number') ?? 0) + 1;

            $sprint = $project->sprints()->create([
                'created_by' => $request->user()->id,
                'number' => $nextNumber,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            $this->syncRequirements($sprint, $validIds);
        });

        return back()->with('status', 'Sprint criada.');
    }

    public function update(
        UpdateSprintRequest $request,
        Project $project,
        Sprint $sprint
    ): RedirectResponse {
        abort_unless($sprint->project_id === $project->id, 404);

        $data = $request->validated();
        $requirementIds = $data['requirement_ids'] ?? [];

        $validIds = $project->requirements()
            ->readyForDev()
            ->whereIn('id', $requirementIds)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($sprint, $data, $validIds) {
            $sprint->update([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            $this->syncRequirements($sprint, $validIds);
        });

        return back()->with('status', 'Sprint atualizada.');
    }

    public function destroy(Project $project, Sprint $sprint): RedirectResponse
    {
        abort_unless($sprint->project_id === $project->id, 404);
        $this->authorize('delete', $sprint);

        DB::transaction(function () use ($sprint) {
            $sprint->requirements()->detach();
            $sprint->delete();
        });

        return back()->with('status', 'Sprint removida.');
    }

    /**
     * @param  list<int>  $requirementIds
     */
    private function syncRequirements(Sprint $sprint, array $requirementIds): void
    {
        $syncData = [];
        foreach ($requirementIds as $position => $id) {
            $syncData[$id] = ['position' => $position];
        }

        $sprint->requirements()->sync($syncData);
    }
}
