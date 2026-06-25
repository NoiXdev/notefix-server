<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API &middot; Notefix</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #faf9f7; color: #1c1917; line-height: 1.6; }
        .wrap { max-width: 48rem; margin: 0 auto; padding: 2.5rem 1.5rem 5rem; }
        header { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
        h1 { font-size: 1.6rem; margin: 0; }
        h2 { font-size: 1.15rem; margin: 2.5rem 0 .5rem; }
        h3 { font-size: .95rem; margin: 1.5rem 0 .4rem; }
        p, li { color: #44403c; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9em;
            background: #efece6; padding: .1em .35em; border-radius: 4px; }
        pre { background: #282a36; color: #f8f8f2; border-radius: 10px; padding: 1rem 1.1rem; overflow-x: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85rem; line-height: 1.5; }
        .method { display: inline-block; font-weight: 700; font-size: .8rem; padding: .15rem .5rem; border-radius: 5px; margin-right: .5rem; }
        .get { background: #dcfce7; color: #166534; }
        .post { background: #ffedd5; color: #9a3412; }
        .endpoint { background: #fff; border: 1px solid #e7e5e4; border-radius: 10px; padding: 1rem 1.25rem; margin: .75rem 0; }
        .logout { font-size: .85rem; }
        .logout button { background: none; border: 1px solid #d6d3d1; border-radius: 6px; padding: .3rem .7rem; cursor: pointer; color: inherit; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; margin: .5rem 0; }
        td { padding: .3rem .5rem; border-bottom: 1px solid #efece6; vertical-align: top; }
        td:first-child { white-space: nowrap; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Sync API</h1>
        <form class="logout" method="POST" action="{{ url('/logout') }}">@csrf<button type="submit">Sign out</button></form>
    </header>

    <p>The desktop app syncs each server context against these endpoints. The server is the source of truth; conflicts resolve last-write-wins and overwritten note versions are kept in the history.</p>

    <h2>Authentication</h2>
    <p>All endpoints require a bearer token obtained through the app's browser sign-in. Send it on every request:</p>
    <pre>Authorization: Bearer &lt;access-token&gt;
Accept: application/json</pre>

    <h2>Pull changes</h2>
    <div class="endpoint">
        <span class="method get">GET</span><code>/api/workspaces/{workspace}/changes?since={seq}</code>
        <p>Everything in the workspace changed after the <code>since</code> cursor (tombstones included), oldest first.</p>
    </div>
    <h3>Response</h3>
    <pre>{
  "cursor": 42,
  "folders": [
    { "id": "uuid", "parentId": null, "name": "Work", "icon": null,
      "color": null, "sort": null, "position": 0,
      "updatedAt": "2026-06-25T09:00:00+00:00", "deletedAt": null }
  ],
  "notes": [
    { "id": "uuid", "folderId": "uuid", "content": "&lt;p&gt;Hi&lt;/p&gt;",
      "pinned": false, "archived": false, "color": null, "dueAt": null,
      "position": 0, "updatedAt": "2026-06-25T09:00:00+00:00", "deletedAt": null }
  ]
}</pre>
    <p>Store <code>cursor</code> and pass it as <code>since</code> on the next pull.</p>

    <h2>Push changes</h2>
    <div class="endpoint">
        <span class="method post">POST</span><code>/api/workspaces/{workspace}/changes</code>
        <p>Send locally changed folders/notes. Requires the <code>editor</code> or <code>owner</code> role. Each entity carries its client <code>updatedAt</code>; set <code>deletedAt</code> to delete (tombstone).</p>
    </div>
    <h3>Request body</h3>
    <pre>{
  "notes": [
    { "id": "uuid", "folderId": null, "content": "&lt;p&gt;Edited&lt;/p&gt;",
      "pinned": false, "archived": false, "position": 0,
      "updatedAt": "2026-06-25T09:05:00+00:00" }
  ],
  "folders": []
}</pre>
    <p>The response mirrors the pull shape: the canonical applied entities plus the new <code>cursor</code>. A push whose <code>updatedAt</code> is older than the server's stored version is ignored (server wins), and the replaced note content is kept as a revision.</p>

    <h2>Fields</h2>
    <table>
        <tr><td><code>id</code></td><td>UUID, created by the client so offline creation is stable.</td></tr>
        <tr><td><code>updatedAt</code></td><td>ISO 8601. Drives last-write-wins.</td></tr>
        <tr><td><code>deletedAt</code></td><td>ISO 8601 or null. Non-null = tombstone.</td></tr>
        <tr><td><code>cursor</code></td><td>Monotonic per workspace. Opaque sync position.</td></tr>
    </table>
</div>
</body>
</html>
