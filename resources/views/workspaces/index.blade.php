<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workspaces &middot; Notefix</title>
    <style>
        body { margin:0; font-family:system-ui,sans-serif; background:#fef9c3; color:#1c1917; }
        .wrap { max-width:34rem; margin:0 auto; padding:2.5rem 1.5rem 5rem; }
        h1 { font-size:1.6rem; margin:0 0 1rem; }
        .row { display:flex; align-items:center; justify-content:space-between; background:#fff; border:1px solid #e7d27a; border-radius:10px; padding:.8rem 1rem; margin-bottom:.6rem; }
        .badge { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; background:#fde047; color:#1c1917; border-radius:6px; padding:.1rem .45rem; }
        a.btn { text-decoration:none; padding:.4rem .8rem; border-radius:8px; background:#eab308; color:#1c1917; font-weight:600; font-size:.85rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Your workspaces</h1>
        @if (session('status'))<p style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:.6rem .9rem;">{{ session('status') }}</p>@endif
        @foreach ($workspaces as $ws)
            <div class="row">
                <span>{{ $ws->name }} <span class="badge">{{ $ws->pivot->role }}</span></span>
                @if ($ws->pivot->role === 'owner')
                    <a class="btn" href="{{ route('workspaces.share', $ws) }}">Share</a>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
