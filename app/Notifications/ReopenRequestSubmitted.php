<?php

namespace App\Notifications;

use App\Models\ReopenRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada ao decisor (QA ou PM) quando alguém solicita reabertura.
 */
class ReopenRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReopenRequest $reopenRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $rr = $this->reopenRequest;
        $rr->loadMissing(['requirement.project', 'requester']);

        $requirement = $rr->requirement;
        $project = $requirement?->project;
        $requester = $rr->requester;

        $projectName = $project?->name ?? '—';
        $requirementTitle = $requirement?->title ?? '—';
        $projectId = $project?->id ?? 0;

        $url = url("/projects/{$projectId}/reopen-requests/{$rr->id}");

        $subjectPrefix = $rr->scope->value === 'requirement_approval'
            ? 'Pedido de reabertura do analista'
            : 'Pedido de reabertura do QA';

        return (new MailMessage)
            ->subject("[{$projectName}] {$subjectPrefix}: {$requirementTitle}")
            ->greeting('Olá!')
            ->line("**{$requester?->name}** solicitou a reabertura de **{$rr->scope->label()}** para o requisito \"{$requirementTitle}\".")
            ->line('**Motivo informado:**')
            ->line($rr->reason)
            ->action('Revisar solicitação', $url)
            ->line('Na tela você pode aprovar ou recusar (se recusar, deve informar o motivo).')
            ->line("Projeto: {$projectName}");
    }
}
