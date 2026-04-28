<?php

namespace App\Observers;

use App\Models\TestCase;
use App\Services\Audit\AuditLogger;

class TestCaseObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(TestCase $case): void
    {
        $this->audit->record($case, 'created', [], [
            'title' => $case->title,
            'test_plan_id' => $case->test_plan_id,
            'requirement_id' => $case->requirement_id,
            'origin' => $case->requirement_id ? 'generated_from_requirement' : 'manual',
        ]);
    }

    public function updated(TestCase $case): void
    {
        $diff = $this->audit->diff($case);
        if ($diff !== []) {
            $this->audit->record($case, 'updated', $diff);
        }
    }

    public function deleted(TestCase $case): void
    {
        $this->audit->record($case, $case->isForceDeleting() ? 'force_deleted' : 'deleted');
    }

    public function restored(TestCase $case): void
    {
        $this->audit->record($case, 'restored');
    }
}
