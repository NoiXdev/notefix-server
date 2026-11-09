<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\DesktopClient;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Http\Responses\SimpleViewResponse;

beforeEach(function () {
    Setting::put('setupComplete', 'true');
});

it('binds the Passport authorization view response in the container', function () {
    // Passport 13 ships no default binding for this contract; the app must
    // register an authorization view. Without it, AuthorizationController@authorize
    // can't have its method dependencies resolved and every /oauth/authorize 500s.
    expect(app(AuthorizationViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class);
});

it('authorizes the first-party desktop client without a consent screen', function () {
    $user = User::factory()->create();
    $client = DesktopClient::ensure();

    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $res = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => DesktopClient::REDIRECT,
        'response_type' => 'code',
        'scope' => '',
        'state' => 'xyz123',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    // First-party client skips consent → straight redirect back to the app with a code.
    $res->assertStatus(302);
    expect($res->headers->get('Location'))
        ->toStartWith('notefix://auth')
        ->toContain('code=')
        ->toContain('state=xyz123');
});

it('redirects to a loopback listener on any port (RFC 8252 — debug desktop builds)', function () {
    $user = User::factory()->create();
    $client = DesktopClient::ensure();

    $res = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://127.0.0.1:53124/auth',
        'response_type' => 'code',
        'scope' => '',
        'state' => 'loop',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]));

    $res->assertStatus(302);
    expect($res->headers->get('Location'))->toStartWith('http://127.0.0.1:53124/auth?code=');
});

it('adds the loopback redirect to a desktop client created before it existed', function () {
    app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        DesktopClient::NAME, [DesktopClient::REDIRECT], false, null,
    );

    $client = DesktopClient::ensure();

    expect($client->fresh()->redirect_uris)
        ->toBe([DesktopClient::REDIRECT, DesktopClient::LOOPBACK]);
});

it('renders the consent screen for a third-party client instead of 500', function () {
    $user = User::factory()->create();

    // A confidential, user-owned (third-party) client → consent is NOT skipped,
    // so the authorization view actually renders.
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Third Party', ['https://example.test/callback'], true, $user,
    );

    $res = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => '',
        'state' => 'abc',
    ]));

    $res->assertOk();
    $res->assertSee('Third Party');
});
