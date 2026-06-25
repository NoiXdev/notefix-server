<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join {{ $workspace->name }} &middot; Notefix</title>
    <style>
        body { margin:0; font-family:system-ui,sans-serif; background:#fef9c3; color:#1c1917; }
        .wrap { max-width:30rem; margin:0 auto; padding:5rem 1.5rem; text-align:center; }
        .card { background:#fff; border:1px solid #e7d27a; border-radius:12px; padding:2rem; }
        button { padding:.7rem 1.4rem; border:0; border-radius:8px; background:#eab308; color:#1c1917; font-weight:700; cursor:pointer; font-size:1rem; }
        .role { font-weight:700; }
    </style>
</head>
<body>
    <div class="wrap"><div class="card">
        <h1>Join &ldquo;{{ $workspace->name }}&rdquo;</h1>
        <p>You were invited as <span class="role">{{ ucfirst($invitation->role->value) }}</span>.</p>
        <form method="POST" action="{{ route('invite.accept', $invitation->token) }}">
            @csrf
            <button>Accept &amp; join</button>
        </form>
    </div></div>
</body>
</html>
