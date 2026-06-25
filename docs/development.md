# Development

`notefix-server` is a Laravel 13 application, run locally with DDEV (PHP 8.3, MariaDB).

## Run it

```bash
ddev start
ddev composer install
ddev artisan migrate
ddev launch
```

On a fresh database the app redirects to `/setup`, where you create the first
administrator and name the instance. After that the admin panel lives at
`/admin` (admins only).

## Quality gates

```bash
ddev artisan test                     # Pest
ddev exec vendor/bin/pint             # format (add --test to check only)
ddev exec vendor/bin/phpstan analyse  # Larastan
```

## Stack

- Laravel 13 · PHP 8.3 · MariaDB (via DDEV)
- Web auth: Laravel **Fortify** — registration, login, password reset, TOTP two-factor (Notefix-styled views)
- API auth: Laravel **Passport** (OAuth2 + PKCE) — for the desktop app's browser login flow
- Admin: Filament 5, at `/admin`
- Tests: Pest · Format: Pint · Static analysis: Larastan

## Web & auth

- Branded landing at `/`; `/login`, `/register`, `/security` (2FA setup).
- **Self-registration** is gated by the `registrationEnabled` setting — flip it on the Filament "Instance settings" page (`/admin/instance-settings`). When off, `/register` redirects to login and the registration POST is rejected.
- **Two-factor (TOTP)**: users enable + confirm it on `/security` (QR + recovery codes). With the `mfaEnforced` setting on, authenticated users without confirmed 2FA are sent to `/security` until they comply.

## Sharing (self-service)

A workspace **owner** shares without the admin panel (package **W+**):

- `/workspaces` — the signed-in user's workspaces (owned + member). Owned ones link to "Share".
- `/workspaces/{id}/share` — owner-only (`WorkspacePolicy::manage`): change/remove members and create **single-use** invite links (role + optional expiry) or revoke open ones. A last-owner guard keeps every workspace with ≥ 1 owner.
- `/invite/{token}` — an invitee (logged in) joins as the invited role; the invite is consumed on first accept. Expired/used/unknown links show a friendly error.
- The joined workspace appears in the desktop picker via `GET /api/workspaces` (no client change).

## Data model

- `users` (`is_admin` gates the admin panel; Fortify two-factor columns)
- `workspaces` (`personal` | `shared`, owned by a user; `sync_cursor` allocates sequence numbers)
- `workspace_members` (role: `owner` | `editor` | `viewer`)
- `instance_settings` (key/value: `instanceName`, `setupComplete`, `registrationEnabled`, `mfaEnforced`)
- `notes` / `folders` — UUID-keyed (client-provided), `sync_seq` for ordering, soft-deleted (tombstones)
- `revisions` — overwritten note content, kept by last-write-wins

Every new user automatically gets a personal workspace with themselves as owner.

## Sync API

Server-authoritative sync, per workspace, behind `auth:api` (Passport). Members
read, editors/owners write. Conflicts resolve last-write-wins by `updatedAt`;
the replaced note content is kept as a revision.

- `GET /api/workspaces` — the authenticated user's workspaces (`id`, `name`, `role`); the desktop app shows these in a picker so each server context binds to one workspace.
- `GET /api/workspaces/{workspace}/changes?since={seq}` — everything changed after the cursor (tombstones included).
- `POST /api/workspaces/{workspace}/changes` — apply a batch of folder/note upserts/deletes; returns the canonical entities + new cursor.
- `GET /api/workspaces/{workspace}/images` — image manifest `[{ path, size, hash }]` (members).
- `POST /api/workspaces/{workspace}/images` — upload one image (multipart `path` + `file`, editors+; idempotent, `image/*` ≤ 10 MB, safe relpath). Blobs live on the `local` disk under `images/{workspace}/{path}`.
- `GET /api/workspaces/{workspace}/image?path=…` — download one image's bytes (members).

The desktop client (package **C1**) syncs the bound workspace against these
endpoints: it caches notes/folders locally, marks edited rows dirty (a write
queue), pushes them then pulls on a 15s timer / on demand, and applies the
server's canonical rows (LWW resolved server-side). After the note phase it
transfers referenced image blobs (**S2b**): it diffs the local images against
the manifest and uploads/downloads the difference (path-addressed, non-fatal).
A human-readable reference is served at `/docs/api` (login required). Web sign-in
lives at `/login`.

## Desktop auth bridge

The desktop app signs in through the browser with OAuth2 authorization-code +
PKCE (no client secret). A first-party "Notefix Desktop" Passport client with the
`notefix://auth` redirect is created on demand and skips the consent screen.

- `GET /api/oauth/config` (unauthenticated) → `{ clientId, authorizeUrl, tokenUrl, scopes }`.
- The app opens `authorizeUrl` in the browser; after login the server redirects to
  `notefix://auth?code=…&state=…`, which the app exchanges at the token endpoint
  (verifying its PKCE `state`) and stores in the OS keychain.

## Out of scope (later packages)

- Server-side orphan-image GC (blobs of deleted notes are kept)
- Invitations and richer sharing UX beyond the admin panel
