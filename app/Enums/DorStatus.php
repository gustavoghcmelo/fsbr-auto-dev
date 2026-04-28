<?php

namespace App\Enums;

enum DorStatus: string
{
    case Draft = 'draft';
    case InQaValidation = 'in_qa_validation';
    case Approved = 'approved';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::InQaValidation => 'Em validação pelo QA',
            self::Approved => 'Aprovado',
            self::Blocked => 'Bloqueado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'grey',
            self::InQaValidation => 'orange',
            self::Approved => 'positive',
            self::Blocked => 'negative',
        };
    }
}
