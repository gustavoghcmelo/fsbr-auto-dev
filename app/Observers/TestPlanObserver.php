<?php

namespace App\Observers;

use App\Models\TestPlan;
use App\Services\Audit\AuditLogger;

class TestPlanObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(TestPlan $plan): void
    {
        $this->audit->record($plan, 'created', [], [
            'name' => $plan->name,
            'status' => $plan->status->value,
        ]);
    }

    public function updated(TestPlan $plan): void
    {
        $diff = $this->audit->diff($plan);
        if ($diff !== []) {
            $this->audit->record($plan, 'updated', $diff);
        }
    }

    public function deleted(TestPlan $plan): void
    {
        $this->audit->record($plan, $plan->isForceDeleting() ? 'force_deleted' : 'deleted');
    }

    public function restored(TestPlan $plan): void
    {
        $this->audit->record($plan, 'restored');
    }
}
