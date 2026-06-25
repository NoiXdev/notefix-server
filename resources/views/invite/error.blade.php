<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation &middot; Notefix</title>
    <style>
        body { margin:0; font-family:system-ui,sans-serif; background:#fef9c3; color:#1c1917; }
        .wrap { max-width:30rem; margin:0 auto; padding:5rem 1.5rem; text-align:center; }
        .card { background:#fff; border:1px solid #e7d27a; border-radius:12px; padding:2rem; }
        a { color:#854d0e; }
    </style>
</head>
<body>
    <div class="wrap"><div class="card">
        <h1>Invitation unavailable</h1>
        <p>This invitation is no longer valid — it may have expired or already been used.</p>
        <p><a href="{{ url('/workspaces') }}">Go to your workspaces</a></p>
    </div></div>
</body>
</html>
