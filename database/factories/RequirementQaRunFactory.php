<?php

namespace Database\Factories;

use App\Enums\FinalQaStatus;
use App\Models\Requirement;
use App\Models\RequirementQaRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequirementQaRun>
 */
class RequirementQaRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requirement_id' => fn () => Requirement::factory()->create()->id,
            'evaluated_by' => fn () => User::factory()->create()->id,
            'run_number' => 1,
            'status' => FinalQaStatus::Pending,
            'observations' => null,
            'evaluated_at' => null,
        ];
    }
}
