<?php

namespace App\Observers;

use App\Models\ReopenRequest;
use App\Services\Audit\AuditLogger;

class ReopenRequestObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(ReopenRequest $request): void
    {
        $this->audit->record($request, 'reopen.requested', [], [
            'requirement_id' => $request->requirement_id,
            'scope' => $request->scope->value,
            'requested_by' => $request->requested_by,
            'reason' => $request->reason,
        ]);
    }

    public function updated(ReopenRequest $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        $event = match ($request->status->value) {
            'approved' => 'reopen.approved',
            'denied' => 'reopen.denied',
            default => 'reopen.updated',
        };

        $this->audit->record($request, $event, [], [
            'scope' => $request->scope->value,
            'decided_by' => $request->decided_by,
            'decision_reason' => $request->decision_reason,
        ]);
    }
}
