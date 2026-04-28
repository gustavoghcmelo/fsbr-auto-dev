<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Paused = 'paused';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planejamento',
            self::Active => 'Em andamento',
            self::Paused => 'Pausado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planning => 'grey',
            self::Active => 'primary',
            self::Paused => 'warning',
            self::Delivered => 'positive',
            self::Cancelled => 'negative',
        };
    }
}
