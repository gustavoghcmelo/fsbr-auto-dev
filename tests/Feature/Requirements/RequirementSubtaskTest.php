<?php

namespace Tests\Feature\Requirements;

use App\Enums\SubtaskKind;
use App\Models\Requirement;
use App\Models\RequirementSubtask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequirementSubtaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{SubtaskKind}>
     */
    public static function kindProvider(): iterable
    {
        yield 'backend' => [SubtaskKind::Backend];
        yield 'frontend' => [SubtaskKind::Frontend];
        yield 'integration' => [SubtaskKind::Integration];
        yield 'tests' => [SubtaskKind::Tests];
    }

    #[Test]
    #[DataProvider('kindProvider')]
    public function it_creates_subtask_with_all_kinds(SubtaskKind $kind): void
    {
        $subtask = RequirementSubtask::factory()->create(['kind' => $kind]);

        $this->assertSame($kind, $subtask->fresh()->kind);
    }

    #[Test]
    public function it_cascades_when_requirement_deleted(): void
    {
        $subtask = RequirementSubtask::factory()->create();
        $requirementId = $subtask->requirement_id;

        Requirement::query()->whereKey($requirementId)->delete();

        $this->assertDatabaseMissing('requirement_subtasks', ['id' => $subtask->id]);
    }

    #[Test]
    public function it_nulls_assignee_when_user_deleted(): void
    {
        $assignee = User::factory()->create();
        $subtask = RequirementSubtask::factory()->create(['assignee_id' => $assignee->id]);

        $assignee->delete();

        $this->assertNull($subtask->fresh()->assignee_id);
    }

    #[Test]
    public function it_loads_via_requirement_subtasks_relation(): void
    {
        $requirement = Requirement::factory()->create();
        $subtask = RequirementSubtask::factory()->create([
            'requirement_id' => $requirement->id,
        ]);

        $this->assertTrue($requirement->subtasks->contains($subtask));
        $this->assertTrue($subtask->requirement->is($requirement));
    }
}
