<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Models\Setting::get('instanceName', 'Notefix') }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #1c1917;
            background: linear-gradient(180deg, #fef9c3 0%, #fefce8 100%); }
        main { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem 1.5rem; }
        .mark { width: 64px; height: 64px; border-radius: 16px; background: #fde047; display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800; color: #854d0e; margin-bottom: 1.5rem; box-shadow: 0 1px 0 #eab308 inset; }
        h1 { font-size: 2.1rem; margin: 0 0 .5rem; }
        p.lead { font-size: 1.05rem; color: #57534e; max-width: 34rem; margin: 0 0 2rem; line-height: 1.6; }
        .actions { display: flex; gap: .75rem; flex-wrap: wrap; justify-content: center; }
        a.btn { text-decoration: none; padding: .7rem 1.4rem; border-radius: 9px; font-weight: 600; font-size: .95rem; }
        a.primary { background: #eab308; color: #1c1917; }
        a.primary:hover { background: #ca8a04; }
        a.ghost { border: 1px solid #e7d27a; color: #854d0e; }
        a.ghost:hover { background: #fef08a; }
        footer { text-align: center; padding: 1.5rem; font-size: .8rem; color: #a8a29e; }
        footer a { color: #a16207; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">N</div>
        <h1>{{ \App\Models\Setting::get('instanceName', 'Notefix') }}</h1>
        <p class="lead">Your notes, kept in sync. Sign in to reach your workspaces from the Notefix app, or create an account to get started.</p>
        <div class="actions">
            @auth
                <a class="btn primary" href="{{ url('/workspaces') }}">My workspaces</a>
            @else
                <a class="btn primary" href="{{ url('/login') }}">Sign in</a>
                @if (\App\Models\Setting::get('registrationEnabled', 'true') === 'true')
                    <a class="btn ghost" href="{{ url('/register') }}">Create account</a>
                @endif
            @endauth
        </div>
    </main>
    <footer>
        Powered by <a href="https://noix.dev">Notefix</a>
    </footer>
</body>
</html>
