<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client as BaseClient;

class PassportClient extends BaseClient
{
    /**
     * Skip the consent screen for first-party clients (e.g. the Notefix
     * desktop app on the user's own self-hosted instance).
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->firstParty();
    }
}
