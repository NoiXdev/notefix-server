<?php

use App\Models\Revision;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(fn () => Setting::put('setupComplete', 'true'));

function pushNote(int $ws, array $fields): void
{
    test()->postJson("/api/workspaces/{$ws}/changes", [
        'notes' => [array_merge(['updatedAt' => now()->toIso8601String(), 'position' => 0], $fields)],
    ])->assertOk();
}

it('purges the plaintext history when a note becomes protected and keeps none after', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();

    pushNote($ws->id, ['id' => $nid, 'content' => '<p>v1</p>']);
    $this->travel(1)->seconds();
    pushNote($ws->id, ['id' => $nid, 'content' => '<p>v2</p>']);
    expect(Revision::where('note_id', $nid)->count())->toBe(1); // v1 kept as history

    $this->travel(1)->seconds();
    pushNote($ws->id, ['id' => $nid, 'content' => 'cipher-1==', 'protected' => true]);
    expect(Revision::where('note_id', $nid)->count())->toBe(0); // plaintext history gone

    $this->travel(1)->seconds();
    pushNote($ws->id, ['id' => $nid, 'content' => 'cipher-2==', 'protected' => true]);
    expect(Revision::where('note_id', $nid)->count())->toBe(0); // no ciphertext history either
});

it('resumes history after an explicit unprotect', function () {
    $user = User::factory()->create();
    $ws = $user->ownedWorkspaces->first();
    Passport::actingAs($user);
    $nid = Str::uuid()->toString();
    pushNote($ws->id, ['id' => $nid, 'content' => 'cipher-1==', 'protected' => true]);
    $this->travel(1)->seconds();
    pushNote($ws->id, ['id' => $nid, 'content' => '<p>plain again</p>', 'protected' => false]);
    $this->travel(1)->seconds();
    pushNote($ws->id, ['id' => $nid, 'content' => '<p>edited</p>', 'protected' => false]);
    expect(Revision::where('note_id', $nid)->pluck('content')->all())->toBe(['<p>plain again</p>']);
});
