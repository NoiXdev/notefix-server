@php($user = auth()->user())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Security &middot; Notefix</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #fef9c3; color: #1c1917; }
        .wrap { max-width: 34rem; margin: 0 auto; padding: 2.5rem 1.5rem 5rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.1rem; margin: 2rem 0 .5rem; }
        p { color: #57534e; }
        .card { background: #fff; border: 1px solid #e7d27a; border-radius: 12px; padding: 1.25rem 1.5rem; margin-top: 1rem; }
        button { padding: .6rem 1.1rem; border: 0; border-radius: 8px; background: #eab308; color: #1c1917; font-weight: 600; cursor: pointer; }
        button:hover { background: #ca8a04; }
        button.ghost { background: none; border: 1px solid #e7d27a; color: #854d0e; }
        button.danger { background: #dc2626; color: #fff; }
        input { padding: .55rem .7rem; border: 1px solid #d6d3d1; border-radius: 8px; letter-spacing: .15em; }
        .codes { font-family: ui-monospace, Menlo, monospace; background: #fffdf0; border: 1px solid #e7d27a; border-radius: 8px; padding: .75rem 1rem; columns: 2; }
        .err { color: #b91c1c; font-size: .85rem; }
        .ok { color: #15803d; font-weight: 600; }
        .qr { background: #fff; display: inline-block; padding: .5rem; border-radius: 8px; }
        a { color: #a16207; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Security</h1>
    <p>Two-factor authentication adds a code from your phone on top of your password.</p>

    @if (! $user->two_factor_secret)
        <div class="card">
            <h2>Two-factor authentication</h2>
            <p>Currently off.</p>
            <form method="POST" action="{{ url('/user/two-factor-authentication') }}">@csrf<button type="submit">Enable</button></form>
        </div>
    @elseif (! $user->two_factor_confirmed_at)
        <div class="card">
            <h2>Scan this code</h2>
            <p>Scan with your authenticator app, then enter the 6-digit code to finish.</p>
            <div class="qr">{!! $user->twoFactorQrCodeSvg() !!}</div>
            <h2>Recovery codes</h2>
            <p>Save these somewhere safe — each works once if you lose your device.</p>
            <div class="codes">@foreach ($user->recoveryCodes() as $code)<div>{{ $code }}</div>@endforeach</div>
            <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" style="margin-top:1rem; display:flex; gap:.5rem;">
                @csrf
                <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" autofocus>
                <button type="submit">Confirm</button>
            </form>
            @error('code') <div class="err">{{ $message }}</div> @enderror
            @error('confirmTwoFactorAuthentication') <div class="err">{{ $message }}</div> @enderror
            <form method="POST" action="{{ url('/user/two-factor-authentication') }}" style="margin-top:.75rem;">
                @csrf @method('DELETE')<button type="submit" class="ghost">Cancel</button>
            </form>
        </div>
    @else
        <div class="card">
            <h2>Two-factor authentication <span class="ok">on</span></h2>
            <h2>Recovery codes</h2>
            <div class="codes">@foreach ($user->recoveryCodes() as $code)<div>{{ $code }}</div>@endforeach</div>
            <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}" style="margin-top:1rem; display:inline;">@csrf<button type="submit" class="ghost">Regenerate codes</button></form>
            <form method="POST" action="{{ url('/user/two-factor-authentication') }}" style="margin-top:1rem; display:inline;">@csrf @method('DELETE')<button type="submit" class="danger">Turn off</button></form>
        </div>
    @endif

    <p style="margin-top:2rem;"><a href="{{ url('/docs/api') }}">Back to the API docs</a></p>
</div>
</body>
</html>
