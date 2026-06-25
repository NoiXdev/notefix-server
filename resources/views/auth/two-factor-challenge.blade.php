<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-factor &middot; Notefix</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #fef9c3; color: #1c1917; }
        .card { width: 100%; max-width: 24rem; background: #fff; border: 1px solid #e7d27a; border-radius: 14px; padding: 2rem; }
        h1 { font-size: 1.3rem; margin: 0 0 .5rem; }
        p { color: #78716c; font-size: .9rem; margin: 0 0 1rem; }
        label { display: block; font-size: .82rem; font-weight: 600; margin: 1rem 0 .35rem; }
        input { width: 100%; padding: .6rem .7rem; border: 1px solid #d6d3d1; border-radius: 8px; font-size: .95rem; letter-spacing: .15em; background: #fff; color: inherit; }
        input:focus { outline: 2px solid #fde047; border-color: #eab308; }
        button { width: 100%; margin-top: 1.5rem; padding: .7rem; border: 0; border-radius: 8px;
            background: #eab308; color: #1c1917; font-weight: 600; font-size: .95rem; cursor: pointer; }
        button:hover { background: #ca8a04; }
        .err { color: #b91c1c; font-size: .8rem; margin-top: .35rem; }
        details { margin-top: 1rem; font-size: .85rem; color: #78716c; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Two-factor authentication</h1>
        <p>Enter the code from your authenticator app.</p>
        <form method="POST" action="{{ url('/two-factor-challenge') }}">
            @csrf
            <label for="code">Authentication code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>
            @error('code') <div class="err">{{ $message }}</div> @enderror

            <details>
                <summary>Use a recovery code instead</summary>
                <label for="recovery_code">Recovery code</label>
                <input id="recovery_code" name="recovery_code" autocomplete="off">
                @error('recovery_code') <div class="err">{{ $message }}</div> @enderror
            </details>

            <button type="submit">Verify</button>
        </form>
    </main>
</body>
</html>
