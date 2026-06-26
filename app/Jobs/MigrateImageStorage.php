<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateImageStorage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $workspaceId,
        public string $path,
        public string $sourceDisk,
        public string $targetDisk,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $key = "images/{$this->workspaceId}/{$this->path}";
        $source = Storage::disk($this->sourceDisk);
        $target = Storage::disk($this->targetDisk);

        if (! $source->exists($key)) {
            // Either already migrated (target has it) or the blob is genuinely gone.
            if (! $target->exists($key)) {
                Log::warning("MigrateImageStorage: source missing {$key}");
            }

            return;
        }

        // Idempotent: target already holds an identically-sized copy.
        if ($target->exists($key) && $target->size($key) === $source->size($key)) {
            return;
        }

        $target->put($key, (string) $source->get($key));
    }
}
