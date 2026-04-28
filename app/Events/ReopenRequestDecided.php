<?php

namespace App\Events;

use App\Models\ReopenRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReopenRequestDecided implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ReopenRequest $reopenRequest) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(
            "projects.{$this->reopenRequest->requirement->project_id}"
        )];
    }

    public function broadcastAs(): string
    {
        return 'reopen-request.decided';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->reopenRequest->id,
            'scope' => $this->reopenRequest->scope->value,
            'status' => $this->reopenRequest->status->value,
            'requirement_id' => $this->reopenRequest->requirement_id,
            'requirement_title' => $this->reopenRequest->requirement->title,
            'decided_by' => $this->reopenRequest->decider?->name,
        ];
    }
}
