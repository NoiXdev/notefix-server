<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Share {{ $workspace->name }} &middot; Notefix</title>
    <style>
        body { margin:0; font-family:system-ui,sans-serif; background:#fef9c3; color:#1c1917; }
        .wrap { max-width:34rem; margin:0 auto; padding:2.5rem 1.5rem 5rem; }
        h1 { font-size:1.6rem; margin:0 0 .25rem; } h2 { font-size:1.1rem; margin:2rem 0 .5rem; }
        a { color:#854d0e; }
        .card { background:#fff; border:1px solid #e7d27a; border-radius:12px; padding:1rem 1.25rem; margin-top:1rem; }
        .flash { background:#dcfce7; border:1px solid #86efac; border-radius:8px; padding:.6rem .9rem; margin:1rem 0; }
        .err { background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:.6rem .9rem; margin:1rem 0; color:#b91c1c; }
        .link { background:#f5f5f4; border:1px solid #e7e5e4; border-radius:8px; padding:.5rem .7rem; font-family:ui-monospace,monospace; font-size:.8rem; word-break:break-all; }
        select,input { padding:.4rem; border:1px solid #e7d27a; border-radius:6px; }
        button { padding:.45rem .9rem; border:0; border-radius:8px; background:#eab308; color:#1c1917; font-weight:600; cursor:pointer; }
        button.danger { background:#dc2626; color:#fff; }
        .member { display:flex; align-items:center; gap:.5rem; justify-content:space-between; padding:.4rem 0; border-bottom:1px solid #f0ead2; }
        form.inline { display:inline; }
        label { display:inline-block; margin-right:.75rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Share &ldquo;{{ $workspace->name }}&rdquo;</h1>
        <p><a href="{{ route('workspaces.index') }}">&larr; Your workspaces</a></p>

        @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="err">{{ session('error') }}</div>@endif
        @if (session('invite_link'))
            <div class="card"><strong>Invite link</strong><div class="link">{{ session('invite_link') }}</div></div>
        @endif

        <h2>Members</h2>
        <div class="card">
            @foreach ($members as $m)
                <div class="member">
                    <span>{{ $m->user->name }} <small>{{ $m->user->email }}</small></span>
                    <span>
                        <form class="inline" method="POST" action="{{ route('workspaces.members.update', [$workspace, $m]) }}">
                            @csrf @method('PATCH')
                            <select name="role" onchange="this.form.submit()">
                                @foreach ($roles as $r)
                                    <option value="{{ $r->value }}" @selected($m->role === $r)>{{ ucfirst($r->value) }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form class="inline" method="POST" action="{{ route('workspaces.members.destroy', [$workspace, $m]) }}">
                            @csrf @method('DELETE')
                            <button class="danger">Remove</button>
                        </form>
                    </span>
                </div>
            @endforeach
        </div>

        <h2>Invite a collaborator</h2>
        <div class="card">
            <form method="POST" action="{{ route('workspaces.invitations.store', $workspace) }}">
                @csrf
                <label>Role
                    <select name="role">
                        <option value="viewer">Viewer</option>
                        <option value="editor">Editor</option>
                        <option value="owner">Owner</option>
                    </select>
                </label>
                <label>Expires (optional) <input type="datetime-local" name="expires_at"></label>
                <button>Create invite link</button>
            </form>
        </div>

        @if ($invitations->isNotEmpty())
            <h2>Open invitations</h2>
            <div class="card">
                @foreach ($invitations as $inv)
                    <div class="member">
                        <span class="link">{{ url('/invite/'.$inv->token) }}</span>
                        <span>{{ ucfirst($inv->role->value) }}
                            <form class="inline" method="POST" action="{{ route('workspaces.invitations.destroy', [$workspace, $inv]) }}">
                                @csrf @method('DELETE')
                                <button class="danger">Revoke</button>
                            </form>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
