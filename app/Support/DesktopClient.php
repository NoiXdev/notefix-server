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
     * Find or create the public, PKCE-capable client the desktop app uses.
     */
    public static function ensure(): Client
    {
        /** @var Client|null $client */
        $client = PassportClient::query()->where('name', self::NAME)->first();

        if (! $client) {
            $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
                self::NAME,
                [self::REDIRECT],
                false, // public (no secret) → PKCE
                null,  // first-party (no owning user) → consent skipped
            );
        }

        return $client;
    }
}
