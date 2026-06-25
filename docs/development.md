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
- Auth: Laravel Passport (OAuth2 + PKCE) — for the desktop app's browser login flow
- Admin: Filament 5, at `/admin`
- Tests: Pest · Format: Pint · Static analysis: Larastan

## Data model

- `users` (`is_admin` gates the admin panel)
- `workspaces` (`personal` | `shared`, owned by a user; `sync_cursor` allocates sequence numbers)
- `workspace_members` (role: `owner` | `editor` | `viewer`)
- `instance_settings` (key/value, e.g. `instanceName`, `setupComplete`)
- `notes` / `folders` — UUID-keyed (client-provided), `sync_seq` for ordering, soft-deleted (tombstones)
- `revisions` — overwritten note content, kept by last-write-wins

Every new user automatically gets a personal workspace with themselves as owner.

## Sync API

Server-authoritative sync, per workspace, behind `auth:api` (Passport). Members
read, editors/owners write. Conflicts resolve last-write-wins by `updatedAt`;
the replaced note content is kept as a revision.

- `GET /api/workspaces/{workspace}/changes?since={seq}` — everything changed after the cursor (tombstones included).
- `POST /api/workspaces/{workspace}/changes` — apply a batch of folder/note upserts/deletes; returns the canonical entities + new cursor.

A human-readable reference is served at `/docs/api` (login required). Web sign-in
lives at `/login`.

## Out of scope (later packages)

- Image sync (upload/download/storage) — **S2b**
- The `notefix://` PKCE token exchange driven from the desktop app — **A1**
- The client-side sync engine (cache + write queue) — **C1**
- Invitations and richer sharing UX beyond the admin panel
