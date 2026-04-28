<?php

namespace Database\Factories;

use App\Enums\DevStatus;
use App\Enums\DorStatus;
use App\Enums\FinalQaStatus;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fn () => $this->makeProject()->id,
            'created_by' => fn () => User::factory()->create()->id,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'gherkin' => 'Funcionalidade: '.fake()->words(3, true),
            'status' => RequirementStatus::Draft,
            'order' => 0,
            'priority' => RequirementPriority::Medium,
            'dor_status' => DorStatus::Draft,
            'dev_status' => DevStatus::NotStarted,
        ];
    }

    public function dorApproved(): static
    {
        return $this->state(fn () => [
            'dor_status' => DorStatus::Approved,
            'status' => RequirementStatus::Approved,
            'qa_approved_at' => now(),
            'approved_at' => now(),
            'dor_checklist' => [
                'requirement_clear' => true,
                'figma_aligned' => true,
                'criteria_defined' => true,
                'business_rules_clear' => true,
                'no_ambiguity' => true,
                'testable' => true,
            ],
        ]);
    }

    public function inDevelopment(): static
    {
        return $this->dorApproved()->state(fn () => [
            'dev_status' => DevStatus::InProgress,
            'dev_started_at' => now()->subDay(),
        ]);
    }

    public function devDone(): static
    {
        return $this->dorApproved()->state(fn () => [
            'dev_status' => DevStatus::Done,
            'dev_started_at' => now()->subDays(3),
            'dev_finished_at' => now(),
        ]);
    }

    public function withFinalQaApproved(): static
    {
        return $this->devDone()->afterCreating(function (Requirement $requirement): void {
            $requirement->qaRuns()->create([
                'evaluated_by' => $requirement->created_by,
                'run_number' => 1,
                'status' => FinalQaStatus::Approved,
                'evaluated_at' => now(),
            ]);
        });
    }

    private function makeProject(): Project
    {
        $owner = User::factory()->create();

        return Project::create([
            'name' => fake()->company(),
            'owner_id' => $owner->id,
        ]);
    }
}
