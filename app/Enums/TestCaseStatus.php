<?php

namespace App\Enums;

enum TestCaseStatus: string
{
    case Active = 'active';
    case Deprecated = 'deprecated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Deprecated => 'Descontinuado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Deprecated => 'grey',
        };
    }
}
