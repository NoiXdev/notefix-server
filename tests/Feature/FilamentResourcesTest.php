<?php

use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceType;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Workspaces\WorkspaceResource;
use App\Models\User;

use function Pest\Laravel\actingAs;

function panelAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('renders the Users resource index, create and edit pages', function () {
    $admin = panelAdmin();
    $other = User::factory()->create();

    actingAs($admin);
    $this->get(UserResource::getUrl('index'))->assertSuccessful();
    $this->get(UserResource::getUrl('create'))->assertSuccessful();
    $this->get(UserResource::getUrl('edit', ['record' => $other]))->assertSuccessful();
});

it('renders the Workspaces resource index, create and edit pages', function () {
    $admin = panelAdmin();
    $ws = $admin->ownedWorkspaces()->create(['name' => 'Team', 'type' => WorkspaceType::Shared]);
    $ws->members()->create(['user_id' => $admin->id, 'role' => WorkspaceRole::Owner]);

    actingAs($admin);
    $this->get(WorkspaceResource::getUrl('index'))->assertSuccessful();
    $this->get(WorkspaceResource::getUrl('create'))->assertSuccessful();
    $this->get(WorkspaceResource::getUrl('edit', ['record' => $ws]))->assertSuccessful();
});
