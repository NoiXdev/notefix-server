<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Authorize') }} &middot; Notefix</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f4f2; color: #1c1917; }
        .card { width: 100%; max-width: 28rem; background: #fff; border: 1px solid #e7e5e4; border-radius: 14px;
            padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        p.sub { margin: 0 0 1.25rem; color: #78716c; font-size: .9rem; }
        .scopes { margin: 0 0 1.5rem; padding: .85rem 1rem; background: #fafaf9; border: 1px solid #e7e5e4;
            border-radius: 10px; }
        .scopes h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #78716c;
            margin: 0 0 .5rem; }
        .scopes ul { margin: 0; padding-left: 1.1rem; font-size: .9rem; }
        .scopes li { margin: .2rem 0; }
        .actions { display: flex; gap: .75rem; }
        form { flex: 1; margin: 0; }
        button { width: 100%; padding: .7rem; border: 0; border-radius: 8px; font-weight: 600; font-size: .95rem;
            cursor: pointer; }
        button.approve { background: #eab308; color: #1c1917; }
        button.deny { background: #f5f5f4; color: #44403c; border: 1px solid #e7e5e4; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Authorize :name', ['name' => $client->name]) }}</h1>
        <p class="sub">{{ __('This application wants to access your Notefix account.') }}</p>

        @if (count($scopes) > 0)
            <div class="scopes">
                <h2>{{ __('Permissions') }}</h2>
                <ul>
                    @foreach ($scopes as $scope)
                        <li>{{ $scope->description }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">{{ __('Authorize') }}</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="deny">{{ __('Cancel') }}</button>
            </form>
        </div>
    </div>
</body>
</html>
