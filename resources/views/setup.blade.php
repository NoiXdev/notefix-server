<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup &middot; Notefix</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f4f2; color: #1c1917; }
        .card { width: 100%; max-width: 28rem; background: #fff; border: 1px solid #e7e5e4; border-radius: 14px;
            padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        p.sub { margin: 0 0 1.5rem; color: #78716c; font-size: .9rem; }
        label { display: block; font-size: .82rem; font-weight: 600; margin: 1rem 0 .35rem; }
        input { width: 100%; padding: .6rem .7rem; border: 1px solid #d6d3d1; border-radius: 8px; font-size: .95rem; background: #fff; color: inherit; }
        input:focus { outline: 2px solid #fde047; border-color: #eab308; }
        button { width: 100%; margin-top: 1.5rem; padding: .7rem; border: 0; border-radius: 8px;
            background: #eab308; color: #1c1917; font-weight: 600; font-size: .95rem; cursor: pointer; }
        button:hover { background: #ca8a04; }
        .err { color: #b91c1c; font-size: .8rem; margin-top: .35rem; }
        .hr { border: 0; border-top: 1px solid #f0efed; margin: 1.5rem 0 0; }
        .note { font-size: .78rem; color: #a8a29e; margin-top: 1rem; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Welcome</h1>
        <p class="sub">Set up this instance and create the administrator account.</p>

        <form method="POST" action="{{ url('/setup') }}">
            @csrf

            <label for="instance_name">Instance name</label>
            <input id="instance_name" name="instance_name" value="{{ old('instance_name') }}" required autofocus>
            @error('instance_name') <div class="err">{{ $message }}</div> @enderror

            <hr class="hr">

            <label for="name">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" required>
            @error('name') <div class="err">{{ $message }}</div> @enderror

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email') <div class="err">{{ $message }}</div> @enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            @error('password') <div class="err">{{ $message }}</div> @enderror

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required>

            <button type="submit">Create administrator</button>
        </form>

        <p class="note">This screen only appears until the instance is set up.</p>
    </main>
</body>
</html>
