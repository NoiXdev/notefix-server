<?php

namespace App\Filament\Resources\Workspaces\Schemas;

use App\Enums\WorkspaceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('owner_id')
                    ->relationship('owner', 'name')
                    ->required(),
                Select::make('type')
                    ->options(WorkspaceType::class)
                    ->default('personal')
                    ->required(),
            ]);
    }
}
