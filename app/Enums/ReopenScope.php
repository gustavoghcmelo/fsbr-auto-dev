<?php

namespace App\Enums;

enum ReopenScope: string
{
    case RequirementApproval = 'requirement_approval';
    case QaApproval = 'qa_approval';

    public function label(): string
    {
        return match ($this) {
            self::RequirementApproval => 'Aprovação do analista',
            self::QaApproval => 'Aprovação do QA',
        };
    }

    /**
     * Papel efetivo no projeto que originou o lock e que hoje solicita reabertura.
     */
    public function requesterRole(): string
    {
        return match ($this) {
            self::RequirementApproval => 'requirement_analyst',
            self::QaApproval => 'quality_assurance',
        };
    }

    /**
     * Papel efetivo no projeto que detém o item hoje e decide a reabertura.
     */
    public function deciderRole(): string
    {
        return match ($this) {
            self::RequirementApproval => 'quality_assurance',
            self::QaApproval => 'project_manager',
        };
    }
}
