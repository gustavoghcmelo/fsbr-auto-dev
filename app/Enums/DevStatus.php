<?php

namespace App\Enums;

enum DevStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Não iniciado',
            self::InProgress => 'Em andamento',
            self::Done => 'Concluído',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'grey',
            self::InProgress => 'primary',
            self::Done => 'positive',
        };
    }
}
