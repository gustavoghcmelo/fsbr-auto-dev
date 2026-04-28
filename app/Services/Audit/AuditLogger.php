<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registra eventos de auditoria em `audit_logs`. Serviço central para
 * qualquer módulo que precise rastrear quem fez o quê, quando.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $changes   Diff {campo: {old, new}}
     * @param  array<string, mixed>  $metadata  Contexto extra
     */
    public function record(
        Model $subject,
        string $event,
        array $changes = [],
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'changes' => $changes ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => optional(Request::instance())->ip(),
        ]);
    }

    /**
     * Calcula o diff em formato {campo: {old, new}} para um model que
     * acabou de ser salvo (ou está prestes a ser). Usa `getOriginal`
     * vs atributos atuais. Campos com timestamps automáticos e
     * `updated_at` são descartados.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(Model $model): array
    {
        $diff = [];
        foreach ($model->getChanges() as $field => $newValue) {
            if (in_array($field, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $diff[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        return $diff;
    }
}
