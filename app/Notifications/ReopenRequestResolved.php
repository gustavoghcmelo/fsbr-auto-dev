<?php

namespace App\Notifications;

use App\Models\ReopenRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada ao requester quando o decisor aprova ou recusa a solicitação.
 */
class ReopenRequestResolved extends Notification implements ShouldQueue
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
        $rr->loadMissing(['requirement.project', 'decider']);

        $requirement = $rr->requirement;
        $project = $requirement?->project;
        $decider = $rr->decider;
        $approved = $rr->status->value === 'approved';

        $projectName = $project?->name ?? '—';
        $requirementTitle = $requirement?->title ?? '—';
        $projectId = $project?->id ?? 0;

        $url = url("/projects/{$projectId}/reopen-requests/{$rr->id}");

        $mail = (new MailMessage)
            ->subject(sprintf(
                '[%s] Reabertura %s: %s',
                $projectName,
                $approved ? 'APROVADA' : 'RECUSADA',
                $requirementTitle,
            ))
            ->greeting('Olá!')
            ->line(sprintf(
                'Sua solicitação de reabertura de **%s** foi **%s** por **%s**.',
                $rr->scope->label(),
                $approved ? 'aprovada' : 'recusada',
                $decider?->name ?? 'responsável',
            ));

        if ($rr->decision_reason) {
            $mail->line('**Observação do decisor:**')->line($rr->decision_reason);
        }

        if ($approved) {
            $mail->line('O requisito foi destravado e já está disponível para edição.');
        }

        return $mail
            ->action('Ver solicitação', $url)
            ->line("Projeto: {$projectName}")
            ->line("Requisito: {$requirementTitle}");
    }
}
