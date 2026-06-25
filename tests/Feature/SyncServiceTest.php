<?php

use App\Models\Note;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->sync = new SyncService;
    $this->ws = User::factory()->create()->ownedWorkspaces->first();
});

it('allocates strictly increasing sequence numbers', function () {
    expect($this->sync->allocateSeq($this->ws))->toBe(1)
        ->and($this->sync->allocateSeq($this->ws))->toBe(2)
        ->and($this->sync->allocateSeq($this->ws))->toBe(3)
        ->and($this->ws->fresh()->sync_cursor)->toBe(3);
});

it('pulls only entities above the cursor and includes tombstones', function () {
    Note::create(['id' => Str::uuid()->toString(), 'workspace_id' => $this->ws->id, 'content' => 'old', 'sync_seq' => 1, 'updated_at' => now()]);
    $recent = Note::create(['id' => Str::uuid()->toString(), 'workspace_id' => $this->ws->id, 'content' => 'new', 'sync_seq' => 5, 'updated_at' => now()]);
    $recent->delete(); // tombstone

    $result = $this->sync->pull($this->ws, 3);

    expect($result['notes'])->toHaveCount(1)
        ->and($result['notes']->first()->id)->toBe($recent->id)
        ->and($result['notes']->first()->trashed())->toBeTrue()
        ->and($result['cursor'])->toBe(5);
});
