<?php

namespace App\Enums;

enum SubtaskKind: string
{
    case Backend = 'backend';
    case Frontend = 'frontend';
    case Integration = 'integration';
    case Tests = 'tests';

    public function label(): string
    {
        return match ($this) {
            self::Backend => 'Backend',
            self::Frontend => 'Frontend',
            self::Integration => 'Integração',
            self::Tests => 'Testes',
        };
    }
}
