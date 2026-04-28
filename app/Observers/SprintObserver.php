<?php

namespace App\Observers;

use App\Models\Sprint;
use App\Services\Audit\AuditLogger;

class SprintObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Sprint $sprint): void
    {
        $this->audit->record($sprint, 'created', [], [
            'number' => $sprint->number,
            'project_id' => $sprint->project_id,
            'start_date' => $sprint->start_date?->toDateString(),
            'end_date' => $sprint->end_date?->toDateString(),
        ]);
    }

    public function updated(Sprint $sprint): void
    {
        $diff = $this->audit->diff($sprint);
        if ($diff !== []) {
            $this->audit->record($sprint, 'updated', $diff);
        }
    }

    public function deleted(Sprint $sprint): void
    {
        $this->audit->record($sprint, $sprint->isForceDeleting() ? 'force_deleted' : 'deleted');
    }

    public function restored(Sprint $sprint): void
    {
        $this->audit->record($sprint, 'restored');
    }
}
