<?php

namespace App\Enums;

enum TestCasePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixa',
            self::Medium => 'Média',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'grey',
            self::Medium => 'blue',
            self::High => 'orange',
            self::Critical => 'red',
        };
    }
}
