<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Generated = 'generated';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando',
            self::Processing => 'Processando',
            self::Generated => 'Gerado',
            self::Failed => 'Falhou',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'grey',
            self::Processing => 'primary',
            self::Generated => 'positive',
            self::Failed => 'negative',
        };
    }
}
