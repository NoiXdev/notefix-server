<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    config([
        'mail.default' => 'postal',
        'mail.mailers.postal' => [
            'transport' => 'postal',
            'endpoint' => 'https://postal.example.test',
            'key' => 'api-key-123',
        ],
        'mail.from.address' => 'from@example.test',
        'mail.from.name' => 'Notefix',
    ]);
});

it('posts the message to the Postal send API', function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    Mail::mailer('postal')->raw('Hello body', function ($m) {
        $m->to('rcpt@example.test')->subject('Hi there');
    });

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/api/v1/send/message')
            && $request->hasHeader('X-Server-API-Key', 'api-key-123')
            && in_array('rcpt@example.test', $request['to'])
            && $request['subject'] === 'Hi there'
            && str_contains($request['plain_body'] ?? '', 'Hello body');
    });
});

it('throws when Postal does not return success', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'data' => ['message' => 'bad key']], 200)]);

    expect(fn () => Mail::mailer('postal')->raw('x', fn ($m) => $m->to('a@b.test')->subject('s')))
        ->toThrow(TransportException::class);
});
