<?php

namespace Database\Factories;

use App\Enums\SubtaskKind;
use App\Enums\SubtaskStatus;
use App\Models\Requirement;
use App\Models\RequirementSubtask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequirementSubtask>
 */
class RequirementSubtaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requirement_id' => fn () => Requirement::factory()->create()->id,
            'created_by' => fn () => User::factory()->create()->id,
            'assignee_id' => null,
            'kind' => fake()->randomElement(SubtaskKind::cases()),
            'description' => fake()->sentence(),
            'status' => SubtaskStatus::Todo,
            'order' => 0,
        ];
    }
}
