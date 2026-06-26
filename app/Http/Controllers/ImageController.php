<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Workspace;
use App\Support\ImagePath;
use App\Support\InstanceConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ImageController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request, Workspace $workspace): array
    {
        Gate::authorize('view', $workspace);

        return [
            'data' => Image::where('workspace_id', $workspace->id)
                ->get(['path', 'size', 'hash'])
                ->map(fn (Image $i): array => ['path' => $i->path, 'size' => $i->size, 'hash' => $i->hash])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request, Workspace $workspace): array
    {
        Gate::authorize('update', $workspace);

        $request->validate([
            'path' => ['required', 'string', 'max:512'],
            'file' => ['required', 'image', 'max:10240'], // 10 MB
        ]);
        $path = (string) $request->input('path');
        abort_unless(ImagePath::isSafe($path), 422, 'unsafe path');

        $bytes = (string) file_get_contents($request->file('file')->getRealPath());
        Storage::disk(InstanceConfig::storageDisk())->put("images/{$workspace->id}/{$path}", $bytes);

        $image = Image::firstOrNew(['workspace_id' => $workspace->id, 'path' => $path]);
        $image->size = strlen($bytes);
        $image->hash = hash('sha256', $bytes);
        $image->created_at ??= now();
        $image->save();

        return ['path' => $path, 'size' => $image->size, 'hash' => $image->hash];
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        $path = (string) $request->query('path', '');
        abort_unless(ImagePath::isSafe($path), 422, 'unsafe path');

        $key = "images/{$workspace->id}/{$path}";
        $disk = Storage::disk(InstanceConfig::storageDisk());
        abort_unless($disk->exists($key), 404);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            default => 'application/octet-stream',
        };

        return response((string) $disk->get($key), 200, ['Content-Type' => $mime]);
    }
}
