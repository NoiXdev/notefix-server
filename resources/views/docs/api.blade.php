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
        .put { background: #dbeafe; color: #1e40af; }
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
  "vaultGeneration": 2,
  "vaultRotationPending": false,
  "vaultKeys": {
    "mine": [
      { "generation": 1, "kdfParams": {}, "dekWrapped": "...", "dekCheck": "..." }
    ],
    "recovery": [
      { "generation": 1, "recoverySalt": "...", "dekWrappedRecovery": "...", "dekCheck": "..." }
    ],
    "rotation": [
      { "generation": 2, "kdfParams": {}, "dekWrapped": "...", "dekCheck": "..." }
    ]
  },
  "workspaceRole": "owner",
  "vaultInvites": [
    { "invitationId": 1, "generation": 1 },
    { "invitationId": 2, "generation": null }
  ],
  "folders": [
    { "id": "uuid", "parentId": null, "name": "Work", "locked": false, "icon": null,
      "color": null, "sort": null, "position": 0,
      "updatedAt": "2026-06-25T09:00:00+00:00", "deletedAt": null }
  ],
  "notes": [
    { "id": "uuid", "folderId": "uuid", "content": "&lt;p&gt;Hi&lt;/p&gt;",
      "protected": false, "title": "", "keyGen": null,
      "pinned": false, "archived": false, "color": null, "dueAt": null,
      "position": 0, "updatedAt": "2026-06-25T09:00:00+00:00", "deletedAt": null }
  ]
}</pre>
    <p>Store <code>cursor</code> and pass it as <code>since</code> on the next pull. <code>vaultGeneration</code> and <code>vaultRotationPending</code> reflect the workspace's protected-notes vault (see <a href="#vault">Vault</a> below); <code>vaultKeys</code> holds only the wraps that belong to the caller — their own key per generation they hold (<code>mine</code>), any recovery wraps they created (<code>recovery</code>), and any rotation wraps waiting for them to convert into a key of their own (<code>rotation</code>). <code>workspaceRole</code> is the caller's role; <code>vaultInvites</code> is, for owners, the workspace's open invitations with the generation of their attached vault wrap, or <code>null</code> when no vault wrap is attached (the invitation predates the vault, or a rotation retired its wrap) — empty for non-owners, and empty while the workspace has no vault.</p>

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
    <p>The response mirrors the pull shape: the canonical applied entities plus the new <code>cursor</code>. A push whose <code>updatedAt</code> is older than the server's stored version is ignored (server wins). An overwritten <em>unprotected</em> note's previous content is kept as a revision — a protected note keeps none, since ciphertext must never sit next to (or after) plaintext history.</p>

    <h2>Fields</h2>
    <table>
        <tr><td><code>id</code></td><td>UUID, created by the client so offline creation is stable.</td></tr>
        <tr><td><code>updatedAt</code></td><td>ISO 8601. Drives last-write-wins.</td></tr>
        <tr><td><code>deletedAt</code></td><td>ISO 8601 or null. Non-null = tombstone.</td></tr>
        <tr><td><code>cursor</code></td><td>Monotonic per workspace. Opaque sync position.</td></tr>
        <tr><td><code>protected</code></td><td>Note. Whether its <code>content</code> is ciphertext under the workspace vault.</td></tr>
        <tr><td><code>title</code></td><td>Note. Plaintext title shown even while the body is protected.</td></tr>
        <tr><td><code>keyGen</code></td><td>Note. The vault generation its ciphertext is wrapped under, or null when unprotected or not yet assigned one.</td></tr>
        <tr><td><code>locked</code></td><td>Folder. Whether notes filed under it are protected by default.</td></tr>
    </table>
    <p><strong>Omitting <code>protected</code>, <code>title</code>, <code>keyGen</code>, or <code>locked</code> from a push means "keep the server's current value" — not "clear it".</strong> This lets an older client that has never heard of the vault re-push a note or folder (e.g. after editing an unrelated field) without unprotecting it or wiping its title. Send the field explicitly (including <code>false</code> or <code>""</code>) to actually change it. <code>keyGen</code> follows the same rule with one addition: since it's already nullable, an explicit <code>keyGen: null</code> on a protected note is not "absent" — it clears the stored generation (used while a client waits to be handed a wrap), whereas leaving the key out of the payload entirely keeps whatever generation the server already has.</p>

    <h2 id="vault">Vault</h2>
    <p>A workspace's protected-notes vault lives on the workspace as wrapped keys only — the server never sees a passphrase or an unwrapped data-encryption key (DEK). Every member may read the wraps addressed to them (via the pull envelope above); only the workspace <strong>owner</strong> may create the vault, rotate its key, or manage invites.</p>

    <div class="endpoint">
        <span class="method post">POST</span><code>/api/workspaces/{workspace}/vault</code>
        <p>Create the vault (generation 1) with the caller's wrapped key and the recovery wrap. Owner only. <code>201</code> on success, <code>403</code> for anyone but the owner, <code>409</code> if a vault already exists, <code>422</code> if <code>recovery.recoverySalt</code>/<code>dekWrappedRecovery</code>/<code>dekCheck</code> are missing.</p>
    </div>
    <div class="endpoint">
        <span class="method put">PUT</span><code>/api/workspaces/{workspace}/vault/keys/me</code>
        <p>Replace the caller's own wrapped key for a generation, or convert a rotation wrap addressed to them into one. Any member. <code>200</code> on success, <code>404</code> if they hold neither a key nor a rotation wrap for that generation.</p>
    </div>
    <div class="endpoint">
        <span class="method post">POST</span><code>/api/workspaces/{workspace}/vault/invites/{invitation}</code>
        <p>Attach an invite-wrapped key to an open invitation, for the invitee to pick up once they accept. Owner only. <code>201</code> on success, <code>404</code> if the invitation isn't this workspace's, <code>410</code> if it's closed (accepted or expired), <code>422</code> if the generation doesn't match the vault's current one, <code>409</code> if a wrap is already attached at the current generation (a wrap left over from before a rotation is replaced instead).</p>
    </div>
    <div class="endpoint">
        <span class="method get">GET</span><code>/api/workspaces/{workspace}/vault/invites/{invitation}</code>
        <p>Fetch the attached invite wrap. Open only to the user who accepted that invitation — not even the owner. <code>200</code> on success, <code>403</code> for a non-member, <code>404</code> for anyone else who is a member (including the owner, and including "no wrap attached yet").</p>
    </div>
    <div class="endpoint">
        <span class="method post">POST</span><code>/api/workspaces/{workspace}/vault/invites/{invitation}/accept</code>
        <p>Consume the invite wrap and store the invitee's own wrapped key in its place. Only the user who accepted that invitation. <code>201</code> on success, <code>404</code> if there's no wrap or the caller isn't the invitee, <code>422</code> on a generation mismatch.</p>
    </div>
    <div class="endpoint">
        <span class="method get">GET</span><code>/api/workspaces/{workspace}/vault/invites/by-token/{token}</code>
        <p>Resolve an invite token (the only part of an invitation a user ever sees) to its numeric id. Open only to the owner or the accepting invitee. <code>200</code> with <code>{ "invitationId": 1 }</code>, <code>404</code> otherwise — deliberately, not <code>403</code>, so this can't be used to probe which tokens exist.</p>
    </div>
    <div class="endpoint">
        <span class="method post">POST</span><code>/api/workspaces/{workspace}/vault/rotate</code>
        <p>Advance the vault to a new generation: an own key for the rotator, rotation-wrapped keys (<code>kind: "code"</code>) for every other current member, and optionally a new recovery wrap. Owner only. <code>201</code> on success. <code>422</code> if <code>generation</code> isn't exactly one past the current generation, if the supplied keys don't cover exactly the current members (one per member, no repeats), or if the rotator holds a recovery set and supplies no wrap for the new generation. Invite wraps from an earlier generation are deleted only for invitations that are still open; an accepted invitee keeps theirs, since it still opens the notes sealed before the rotation. Expired invitations are not open either: their wrap stays until the daily prune removes the invitation a week after expiry.</p>
    </div>
    <div class="endpoint">
        <span class="method post">POST</span><code>/api/workspaces/{workspace}/vault/recovery</code>
        <p>Any owner. Adds the caller's own recovery wrap for a generation (one per generation and holder): <code>201</code>; <code>403</code> for non-owners; <code>409</code> when the caller already holds one; <code>422</code> when the generation is ahead of the vault.</p>
    </div>
    <div class="endpoint">
        <span class="method get">GET</span><code>/api/workspaces/{workspace}/members</code>
        <p>List every member with their role and display name, so a client can compute the key set a rotation must cover and label each entry. Any member. <code>200</code> with <code>{ "members": [{ "userId": 1, "role": "owner", "name": "Ada" }] }</code> — <code>name</code> is the account's display name and may be an empty string; no e-mail address is returned. <code>403</code> for a non-member.</p>
    </div>
</div>
</body>
</html>
