<?php

use App\Modules\Login\GoogleCredential;
use Firebase\JWT\JWT;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Config::set('services.google.client_id', 'client-id.apps.googleusercontent.com');
    Cache::forget('google.identity.jwks');
});

test('google credentials require a configured client id', function (): void {
    Config::set('services.google.client_id');

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(InvalidArgumentException::class, 'Google sign-in is not configured.');
});

test('google credentials reject an unexpected signing key response', function (): void {
    Http::shouldReceive('get')
        ->once()
        ->andReturn(mock(PromiseInterface::class));

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(RuntimeException::class, 'Google signing keys could not be loaded.');
});

test('google credentials reject malformed signing keys', function (): void {
    Http::fake([
        'www.googleapis.com/oauth2/v3/certs' => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(RuntimeException::class, 'Google signing keys are invalid.');
});

test('google credentials reject malformed cached signing keys', function (): void {
    Cache::put('google.identity.jwks', 'not-an-array');

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(RuntimeException::class, 'Cached Google signing keys are invalid.');
});

test('google credentials require a valid signature, issuer, and audience', function (): void {
    $key = openssl_pkey_new([
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key === false || ! openssl_pkey_export($key, $private_key)) {
        throw new RuntimeException('The test private key could not be generated.');
    }

    $details = openssl_pkey_get_details($key);

    if ($details === false
        || ! isset($details['rsa']['n'], $details['rsa']['e'])
        || ! is_string($details['rsa']['n'])
        || ! is_string($details['rsa']['e'])) {
        throw new RuntimeException('The test public key could not be generated.');
    }

    Http::fake([
        'www.googleapis.com/oauth2/v3/certs' => Http::response([
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $details['rsa']['n']
                        |> base64_encode(...)
                        |> (static fn ($x) => strtr($x, '+/', '-_'))
                        |> (static fn ($x) => rtrim($x, '=')),
                'e' => $details['rsa']['e']
                        |> base64_encode(...)
                        |> (static fn ($x) => strtr($x, '+/', '-_'))
                        |> (static fn ($x) => rtrim($x, '=')),
            ]],
        ]),
    ]);

    $claims = [
        'iss' => 'https://accounts.google.com',
        'aud' => 'client-id.apps.googleusercontent.com',
        'sub' => '123456789',
        'email' => 'google@example.com',
        'email_verified' => true,
        'name' => 'Google User',
        'given_name' => 'Google',
        'family_name' => 'User',
        'picture' => 'https://example.com/avatar.jpg',
        'iat' => time(),
        'exp' => time() + 300,
    ];

    $GoogleUser = app(GoogleCredential::class)->user(JWT::encode($claims, $private_key, 'RS256', 'test-key'));

    expect($GoogleUser->sub)->toBe('123456789')
        ->and($GoogleUser->email)->toBe('google@example.com')
        ->and($GoogleUser->hasVerifiedEmail())->toBeTrue();

    app(GoogleCredential::class)->user(JWT::encode([
        ...$claims,
        'aud' => 'another-client.apps.googleusercontent.com',
    ], $private_key, 'RS256', 'test-key'));
})->throws(InvalidArgumentException::class);
