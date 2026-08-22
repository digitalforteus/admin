<?php

use App\Helpers\Gravatar;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::swap(new Factory);
});

test('a url normalizes and hashes the email, and the image comes back inline', function (): void {
    expect(Gravatar::url(' MyEmailAddress@example.com '))
        ->toBe('https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=80&d=404&r=g');

    Http::fake([
        '*' => Http::response('image contents', 200, ['Content-Type' => 'image/png']),
    ]);

    expect(Gravatar::image('person@example.com'))
        ->toBe('data:image/png;base64,'.base64_encode('image contents'));
});

test('an image that cannot be reached, was not found, or is not an image is nothing', function (): void {
    foreach ([
        Http::failedConnection(),
        Http::response(status: 404, headers: ['Content-Type' => 'image/png']),
        Http::response('not an image', headers: ['Content-Type' => 'text/plain']),
    ] as $Response) {
        // Stubs accumulate and the first match wins, so each case needs its own client.
        Http::swap(new Factory);
        Http::fake(['*' => $Response]);

        expect(Gravatar::image('person@example.com'))->toBeNull();
    }
});
