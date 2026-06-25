<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account &middot; Notefix</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #fef9c3; color: #1c1917; }
        .card { width: 100%; max-width: 24rem; background: #fff; border: 1px solid #e7d27a; border-radius: 14px; padding: 2rem; }
        h1 { font-size: 1.4rem; margin: 0 0 1.5rem; }
        label { display: block; font-size: .82rem; font-weight: 600; margin: 1rem 0 .35rem; }
        input { width: 100%; padding: .6rem .7rem; border: 1px solid #d6d3d1; border-radius: 8px; font-size: .95rem; background: #fff; color: inherit; }
        input:focus { outline: 2px solid #fde047; border-color: #eab308; }
        button { width: 100%; margin-top: 1.5rem; padding: .7rem; border: 0; border-radius: 8px;
            background: #eab308; color: #1c1917; font-weight: 600; font-size: .95rem; cursor: pointer; }
        button:hover { background: #ca8a04; }
        .err { color: #b91c1c; font-size: .8rem; margin-top: .35rem; }
        .alt { margin-top: 1.25rem; font-size: .85rem; color: #78716c; }
        .alt a { color: #a16207; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Create your account</h1>
        <form method="POST" action="{{ url('/register') }}">
            @csrf
            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus>
            @error('name') <div class="err">{{ $message }}</div> @enderror

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email') <div class="err">{{ $message }}</div> @enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            @error('password') <div class="err">{{ $message }}</div> @enderror

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required>

            <button type="submit">Create account</button>
        </form>
        <p class="alt">Already have an account? <a href="{{ url('/login') }}">Sign in</a></p>
    </main>
</body>
</html>
