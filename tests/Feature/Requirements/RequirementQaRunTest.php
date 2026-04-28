<?php

namespace Tests\Feature\Requirements;

use App\Enums\DevStatus;
use App\Enums\FinalQaStatus;
use App\Models\Requirement;
use App\Models\RequirementQaRun;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequirementQaRunTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enforces_unique_run_number_per_requirement(): void
    {
        $requirement = Requirement::factory()->create();

        RequirementQaRun::factory()->create([
            'requirement_id' => $requirement->id,
            'run_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        RequirementQaRun::factory()->create([
            'requirement_id' => $requirement->id,
            'run_number' => 1,
        ]);
    }

    #[Test]
    public function it_returns_latest_qa_run_via_helper(): void
    {
        $requirement = Requirement::factory()->create();

        RequirementQaRun::factory()->create([
            'requirement_id' => $requirement->id,
            'run_number' => 1,
            'status' => FinalQaStatus::Rejected,
        ]);
        $latest = RequirementQaRun::factory()->create([
            'requirement_id' => $requirement->id,
            'run_number' => 2,
            'status' => FinalQaStatus::Approved,
        ]);

        $this->assertTrue($requirement->latestQaRun->is($latest));
        $this->assertSame(FinalQaStatus::Approved, $requirement->latestQaStatus());
    }

    #[Test]
    public function it_filters_awaiting_final_qa_via_scope(): void
    {
        $awaitingNoRun = Requirement::factory()->create([
            'dev_status' => DevStatus::Done,
        ]);

        $awaitingPending = Requirement::factory()->create([
            'dev_status' => DevStatus::Done,
        ]);
        RequirementQaRun::factory()->create([
            'requirement_id' => $awaitingPending->id,
            'run_number' => 1,
            'status' => FinalQaStatus::Pending,
        ]);

        $approved = Requirement::factory()->create([
            'dev_status' => DevStatus::Done,
        ]);
        RequirementQaRun::factory()->create([
            'requirement_id' => $approved->id,
            'run_number' => 1,
            'status' => FinalQaStatus::Approved,
        ]);

        $notDone = Requirement::factory()->create([
            'dev_status' => DevStatus::InProgress,
        ]);

        $awaiting = Requirement::query()->awaitingFinalQa()->get();

        $this->assertTrue($awaiting->contains($awaitingNoRun));
        $this->assertTrue($awaiting->contains($awaitingPending));
        $this->assertFalse($awaiting->contains($approved));
        $this->assertFalse($awaiting->contains($notDone));
    }
}
