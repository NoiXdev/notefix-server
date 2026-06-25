<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WorkspaceRole: string implements HasLabel
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
