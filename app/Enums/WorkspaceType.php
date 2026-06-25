<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WorkspaceType: string implements HasLabel
{
    case Personal = 'personal';
    case Shared = 'shared';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
