<?php

namespace App\Enums;

// TODO: Consolidar com App\Enums\TestCasePriority em um App\Enums\Priority
// compartilhado durante o plano de refinamento técnico (plano 5).
// Os valores foram mantidos idênticos para permitir a futura unificação
// sem migration de dados.
enum RequirementPriority: string
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
