<?php

namespace App\Enums;

enum RequirementStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Approved => 'Aprovado',
            self::Rejected => 'Rejeitado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'grey',
            self::Approved => 'positive',
            self::Rejected => 'negative',
        };
    }
}
