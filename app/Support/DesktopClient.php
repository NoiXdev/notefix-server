<?php

namespace App\Support;

use App\Models\PassportClient;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class DesktopClient
{
    public const NAME = 'Notefix Desktop';

    public const REDIRECT = 'notefix://auth';

    /**
     * RFC 8252 loopback redirect. The OAuth server ignores the port of a
     * loopback URI, so the app may listen on any free one. Debug desktop
     * builds on macOS use it: the bare dev binary is no app bundle and can't
     * receive `notefix://` links (the installed app would take them).
     */
    public const LOOPBACK = 'http://127.0.0.1/auth';

    /**
     * Find or create the public, PKCE-capable client the desktop app uses.
     * A client created before the loopback redirect existed gains it here.
     */
    public static function ensure(): Client
    {
        /** @var Client|null $client */
        $client = PassportClient::query()->where('name', self::NAME)->first();

        if (! $client) {
            return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
                self::NAME,
                [self::REDIRECT, self::LOOPBACK],
                false, // public (no secret) → PKCE
                null,  // first-party (no owning user) → consent skipped
            );
        }

        /** @var list<string> $uris */
        $uris = $client->getAttribute('redirect_uris');
        if (! in_array(self::LOOPBACK, $uris, true)) {
            $client->forceFill(['redirect_uris' => [...$uris, self::LOOPBACK]])->save();
        }

        return $client;
    }
}
