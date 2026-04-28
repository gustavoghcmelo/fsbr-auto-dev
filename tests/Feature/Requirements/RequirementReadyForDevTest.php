<?php

namespace Tests\Feature\Requirements;

use App\Enums\DorStatus;
use App\Enums\RequirementStatus;
use App\Models\Requirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequirementReadyForDevTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_ready_for_dev_only_when_status_approved_and_qa_approved_and_dor_approved(): void
    {
        $allApproved = Requirement::factory()->create([
            'status' => RequirementStatus::Approved,
            'qa_approved_at' => now(),
            'dor_status' => DorStatus::Approved,
        ]);
        $this->assertTrue($allApproved->isReadyForDev());

        $missingDor = Requirement::factory()->create([
            'status' => RequirementStatus::Approved,
            'qa_approved_at' => now(),
            'dor_status' => DorStatus::Draft,
        ]);
        $this->assertFalse($missingDor->isReadyForDev());

        $missingQa = Requirement::factory()->create([
            'status' => RequirementStatus::Approved,
            'qa_approved_at' => null,
            'dor_status' => DorStatus::Approved,
        ]);
        $this->assertFalse($missingQa->isReadyForDev());

        $missingAnalystApproval = Requirement::factory()->create([
            'status' => RequirementStatus::Draft,
            'qa_approved_at' => now(),
            'dor_status' => DorStatus::Approved,
        ]);
        $this->assertFalse($missingAnalystApproval->isReadyForDev());
    }

    #[Test]
    public function scope_ready_for_dev_remains_compatible_with_legacy_consumers(): void
    {
        $approvedQaApproved = Requirement::factory()->create([
            'status' => RequirementStatus::Approved,
            'qa_approved_at' => now(),
            'dor_status' => DorStatus::Draft,
        ]);

        $approvedNoQa = Requirement::factory()->create([
            'status' => RequirementStatus::Approved,
            'qa_approved_at' => null,
        ]);

        $draftWithQa = Requirement::factory()->create([
            'status' => RequirementStatus::Draft,
            'qa_approved_at' => now(),
        ]);

        $ready = Requirement::query()->readyForDev()->get();

        $this->assertTrue($ready->contains($approvedQaApproved));
        $this->assertFalse($ready->contains($approvedNoQa));
        $this->assertFalse($ready->contains($draftWithQa));
    }
}
