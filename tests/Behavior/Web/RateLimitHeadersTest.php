<?php

use App\Helpers\HttpHeader;
use App\Routes\ApiRoute;
use App\Routes\MiddlewareTag;
use App\Routes\Web;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

test('a throttled route advertises the limit the route declares', function (): void {
    $response = $this->post(Web::login->value, []);

    expect($response->headers->get(HttpHeader::RateLimitLimit->value))->toBe('10')
        ->and($response->headers->get(HttpHeader::RateLimitRemaining->value))->toBe('9')
        ->and($response->headers->get(HttpHeader::RateLimitPolicy->value))->toBe('10;w=60')
        ->and((int) $response->headers->get(HttpHeader::RateLimitReset->value))->toBeGreaterThan(0)
        ->and((int) $response->headers->get(HttpHeader::RateLimitReset->value))->toBeLessThanOrEqual(60)
        ->and($response->headers->get(HttpHeader::XRateLimitLimit->value))->toBe('10')
        ->and($response->headers->get(HttpHeader::XRateLimitRemaining->value))->toBe('9');
});

test('an exhausted route advertises when it may be retried', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->post(Web::login->value, []);
    }

    $response = $this->post(Web::login->value, []);

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get(HttpHeader::RateLimitRemaining->value))->toBe('0')
        ->and($response->headers->get(HttpHeader::RateLimitPolicy->value))->toBe('10;w=60')
        ->and((int) $response->headers->get(HttpHeader::RetryAfter->value))->toBeGreaterThan(0)
        ->and($response->headers->get(HttpHeader::RateLimitReset->value))
        ->toBe($response->headers->get(HttpHeader::RetryAfter->value));
});

test('the tightest of several ceilings is the one advertised', function (): void {
    $path = '/rate-limit-headers-stacked';

    Route::middleware([
        MiddlewareTag::throttle->value.':100,1,loose',
        MiddlewareTag::throttle->value.':10,2,tight',
    ])->get($path, static fn (): string => 'ok');

    $response = $this->get($path);

    expect($response->headers->get(HttpHeader::RateLimitLimit->value))->toBe('10')
        ->and($response->headers->get(HttpHeader::RateLimitRemaining->value))->toBe('9')
        ->and($response->headers->get(HttpHeader::RateLimitPolicy->value))->toBe('10;w=120')
        ->and($response->headers->get(HttpHeader::XRateLimitLimit->value))->toBe('10');
});

test('an unthrottled route advertises no limit', function (): void {
    $response = $this->getJson(ApiRoute::readme->value);

    expect($response->headers->get(HttpHeader::RateLimitLimit->value))->toBeNull()
        ->and($response->headers->get(HttpHeader::RateLimitPolicy->value))->toBeNull();
});

beforeEach(function (): void {
    RateLimiter::clear('');
    cache()->flush();
});
