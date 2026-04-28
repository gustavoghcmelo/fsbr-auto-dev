<?php

namespace App\Events;

use App\Models\RequirementDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequirementDocumentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public RequirementDocument $document) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("projects.{$this->document->project_id}")];
    }

    public function broadcastAs(): string
    {
        return 'requirement-document.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'document' => [
                'id' => $this->document->id,
                'project_id' => $this->document->project_id,
                'filename' => $this->document->original_filename,
                'status' => $this->document->status->value,
                'status_label' => $this->document->status->label(),
                'status_color' => $this->document->status->color(),
                'failure_reason' => $this->document->failure_reason,
            ],
        ];
    }
}
