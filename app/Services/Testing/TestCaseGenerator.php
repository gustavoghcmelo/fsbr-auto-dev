<?php

namespace App\Services\Testing;

use App\Enums\TestCasePriority;
use App\Enums\TestCaseStatus;
use App\Models\Requirement;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Gera test cases para um plano a partir dos requisitos aprovados do
 * projeto. Estratégia "append only": requisitos já cobertos pelo plano
 * não são re-processados, preservando edições manuais do QA.
 */
class TestCaseGenerator
{
    public function __construct(
        private readonly GherkinScenarioParser $parser,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{created: int, skipped_requirements: int}
     */
    public function generateForPlan(TestPlan $plan, User $actor): array
    {
        $coveredRequirementIds = TestCase::query()
            ->where('test_plan_id', $plan->id)
            ->whereNotNull('requirement_id')
            ->pluck('requirement_id')
            ->all();

        $pendingRequirements = Requirement::query()
            ->where('project_id', $plan->project_id)
            ->approved()
            ->whereNotIn('id', $coveredRequirementIds)
            ->orderBy('order')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($pendingRequirements as $requirement) {
            $scenarios = $this->parser->parse($requirement->gherkin ?? '');

            if ($scenarios === []) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($plan, $requirement, $scenarios, $actor, &$created) {
                $baseOrder = ($plan->cases()->max('order') ?? -1) + 1;
                $caseIds = [];

                foreach ($scenarios as $index => $scenario) {
                    $case = TestCase::create([
                        'test_plan_id' => $plan->id,
                        'requirement_id' => $requirement->id,
                        'created_by' => $actor->id,
                        'title' => $scenario['title'],
                        'preconditions' => $scenario['preconditions'] ?: null,
                        'steps' => $scenario['steps'],
                        'expected_result' => $scenario['expected_result'] ?: null,
                        'gherkin' => $scenario['gherkin'],
                        'priority' => TestCasePriority::Medium->value,
                        'status' => TestCaseStatus::Active->value,
                        'order' => $baseOrder + $index,
                    ]);
                    $caseIds[] = $case->id;
                    $created++;
                }

                $this->audit->record(
                    $plan,
                    'cases.generated_from_requirement',
                    [],
                    [
                        'requirement_id' => $requirement->id,
                        'requirement_title' => $requirement->title,
                        'generated_case_ids' => $caseIds,
                        'generated_count' => count($caseIds),
                    ]
                );
            });
        }

        return [
            'created' => $created,
            'skipped_requirements' => $skipped,
        ];
    }
}
