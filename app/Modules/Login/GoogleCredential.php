<?php

namespace App\Modules\Login;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GoogleCredential
{
    /** @param array<string, mixed>|null $rawPayload */
    public function user(string $credential, ?array &$rawPayload = null): GoogleUser
    {
        $client_id = config('services.google.client_id');

        if (! is_string($client_id) || $client_id === '') {
            throw new InvalidArgumentException('Google sign-in is not configured.');
        }

        $keys = Cache::remember('google.identity.jwks', now()->addHour(), static function (): array {
            $Response = Http::get('https://www.googleapis.com/oauth2/v3/certs');

            if (! $Response instanceof Response) {
                throw new RuntimeException('Google signing keys could not be loaded.');
            }

            $keys = $Response->throw()->json();

            if (! is_array($keys)) {
                throw new RuntimeException('Google signing keys are invalid.');
            }

            return $keys;
        });

        if (! is_array($keys)) {
            throw new RuntimeException('Cached Google signing keys are invalid.');
        }

        $claims = JWT::decode($credential, JWK::parseKeySet($keys));

        if (! in_array($claims->iss ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ($claims->aud ?? null) !== $client_id
            || ($claims->email_verified ?? false) !== true) {
            throw new InvalidArgumentException('The Google credential is invalid.');
        }

        /** @var array<string, mixed> $claimsPayload */
        $claimsPayload = (array) $claims;
        $rawPayload = $claimsPayload;

        return GoogleUser::from([
            GoogleUser::sub => $claims->sub ?? '',
            GoogleUser::name => $claims->name ?? '',
            GoogleUser::given_name => $claims->given_name ?? '',
            GoogleUser::family_name => $claims->family_name ?? '',
            GoogleUser::picture => $claims->picture ?? '',
            GoogleUser::email => $claims->email ?? '',
            GoogleUser::email_verified => true,
            GoogleUser::hd => $claims->hd ?? null,
            GoogleUser::id => $claims->sub ?? '',
            GoogleUser::verified_email => true,
            GoogleUser::link => null,
        ]);
    }
}
