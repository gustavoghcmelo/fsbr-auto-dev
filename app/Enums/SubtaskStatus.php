<?php

namespace App\Enums;

enum SubtaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'A fazer',
            self::InProgress => 'Em andamento',
            self::Done => 'Concluída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'grey',
            self::InProgress => 'primary',
            self::Done => 'positive',
        };
    }
}
