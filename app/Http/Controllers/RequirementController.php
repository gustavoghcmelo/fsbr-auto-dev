<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStatus;
use App\Http\Requests\UpdateRequirementRequest;
use App\Models\Project;
use App\Models\Requirement;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequirementController extends Controller
{
    /**
     * Campos considerados "conteúdo" da feature. Ficam imutáveis enquanto
     * o requisito está aprovado — para alterar, o analista precisa voltar
     * o status para Rascunho primeiro.
     *
     * @var list<string>
     */
    private const CONTENT_FIELDS = [
        'title',
        'description',
        'context',
        'acceptance_criteria',
        'gherkin',
    ];

    public function update(
        UpdateRequirementRequest $request,
        Project $project,
        Requirement $requirement
    ): RedirectResponse {
        abort_unless($requirement->project_id === $project->id, 404);

        $data = $request->validated();
        $wasApproved = $requirement->isApproved();
        $willBeApproved = RequirementStatus::from($data['status']) === RequirementStatus::Approved;

        // Caminho de "reabrir": aprovado → rascunho/rejeitado.
        // Limpa o stamp e ignora eventuais edições no corpo no mesmo request.
        if ($wasApproved && ! $willBeApproved) {
            $requirement->update([
                'status' => $data['status'],
                'approved_at' => null,
                'approved_by' => null,
            ]);

            return back()->with('status', 'Requisito reaberto para edição.');
        }

        // Aplica mudanças em memória para avaliar o lock.
        $requirement->fill($data);

        // Lock: se continua aprovado, conteúdo não pode mudar.
        if ($wasApproved && $willBeApproved
            && $requirement->isDirty(self::CONTENT_FIELDS)) {
            throw ValidationException::withMessages([
                'gherkin' => 'Requisito aprovado está bloqueado. Altere o status para Rascunho antes de editar.',
            ]);
        }

        // Stamp de aprovação: rascunho/rejeitado → aprovado.
        if (! $wasApproved && $willBeApproved) {
            $requirement->approved_at = now();
            $requirement->approved_by = $request->user()->id;
        }

        $requirement->save();

        return back()->with('status', 'Requisito atualizado.');
    }

    public function destroy(Project $project, Requirement $requirement): RedirectResponse
    {
        abort_unless($requirement->project_id === $project->id, 404);

        // Requisito aprovado vira fonte de verdade — bloqueio total de
        // edição/remoção independente do papel. Futuramente a "reabertura"
        // virá via pedido aprovado pelo setor seguinte da esteira.
        if ($requirement->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Requisito aprovado não pode ser removido.',
            ]);
        }

        $this->authorize('delete', $requirement);

        $requirement->delete();

        return back()->with('status', 'Requisito removido.');
    }

    /**
     * Handoff do QA: sinaliza que o requisito está pronto para ir para o
     * backlog de desenvolvimento. Exige que o analista já tenha aprovado.
     */
    public function qaApprove(
        Request $request,
        Project $project,
        Requirement $requirement,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($requirement->project_id === $project->id, 404);
        $this->authorize('qaApprove', $requirement);

        if (! $requirement->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Requisito precisa estar aprovado pelo analista antes do OK do QA.',
            ]);
        }

        // Atribuição direta — `qa_approved_*` não estão em #[Fillable]
        // por design (não devem ser alterados via payload de usuário).
        $requirement->qa_approved_at = now();
        $requirement->qa_approved_by = $request->user()->id;
        $requirement->save();

        $audit->record($requirement, 'qa.approved', [], [
            'qa_user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Requisito liberado para desenvolvimento.');
    }

    public function qaRevoke(
        Request $request,
        Project $project,
        Requirement $requirement,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($requirement->project_id === $project->id, 404);
        $this->authorize('qaApprove', $requirement);

        $requirement->qa_approved_at = null;
        $requirement->qa_approved_by = null;
        $requirement->save();

        $audit->record($requirement, 'qa.revoked');

        return back()->with('status', 'Liberação do QA revogada.');
    }
}
