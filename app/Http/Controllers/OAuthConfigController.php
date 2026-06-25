<?php

namespace App\Http\Controllers;

use App\Support\DesktopClient;

class OAuthConfigController extends Controller
{
    /**
     * Public OAuth discovery: the desktop app fetches this with only the
     * server URL, then runs the PKCE browser flow.
     *
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $client = DesktopClient::ensure();

        return [
            'clientId' => (string) $client->getKey(),
            'authorizeUrl' => url('/oauth/authorize'),
            'tokenUrl' => url('/oauth/token'),
            'scopes' => [],
        ];
    }
}
