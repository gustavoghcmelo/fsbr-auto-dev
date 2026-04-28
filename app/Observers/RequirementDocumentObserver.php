<?php

namespace App\Observers;

use App\Events\RequirementDocumentStatusUpdated;
use App\Models\RequirementDocument;

class RequirementDocumentObserver
{
    public function created(RequirementDocument $document): void
    {
        RequirementDocumentStatusUpdated::dispatch($document);
    }

    public function updated(RequirementDocument $document): void
    {
        if ($document->wasChanged(['status', 'failure_reason'])) {
            RequirementDocumentStatusUpdated::dispatch($document);
        }
    }
}
