<?php

use App\Models\PassportClient;

it('exposes the oauth config with a client id and absolute urls', function () {
    $res = $this->getJson('/api/oauth/config')->assertOk()
        ->assertJsonStructure(['clientId', 'authorizeUrl', 'tokenUrl', 'scopes']);

    expect($res->json('clientId'))->not->toBeEmpty()
        ->and($res->json('authorizeUrl'))->toContain('/oauth/authorize')
        ->and($res->json('tokenUrl'))->toContain('/oauth/token');
});

it('creates a public desktop client with the notefix redirect', function () {
    $this->getJson('/api/oauth/config')->assertOk();

    $client = PassportClient::query()->where('name', 'Notefix Desktop')->first();

    expect($client)->not->toBeNull()
        ->and($client->redirect_uris)->toContain('notefix://auth')
        ->and($client->confidential())->toBeFalse()
        ->and($client->firstParty())->toBeTrue();
});
