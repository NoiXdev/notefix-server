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
- `workspaces` (`personal` | `shared`, owned by a user)
- `workspace_members` (role: `owner` | `editor` | `viewer`)
- `instance_settings` (key/value, e.g. `instanceName`, `setupComplete`)

Every new user automatically gets a personal workspace with themselves as owner.

## Out of scope (later packages)

- Sync / data API for notes, folders, revisions and images
- The `notefix://` PKCE token exchange driven from the desktop app
- Invitations and sharing UX beyond the admin panel
