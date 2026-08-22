<?php

use App\Helpers\HttpHeader;
use App\Routes\ApiRoute;
use App\Routes\MiddlewareTag;
use App\Routes\Web;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

test('a throttled route advertises the limit it declares, and when an exhausted one may be retried', function (): void {
    $response = $this->post(Web::login->value, []);

    expect($response->headers->get(HttpHeader::RateLimitLimit->value))->toBe('10')
        ->and($response->headers->get(HttpHeader::RateLimitRemaining->value))->toBe('9')
        ->and($response->headers->get(HttpHeader::RateLimitPolicy->value))->toBe('10;w=60')
        ->and((int) $response->headers->get(HttpHeader::RateLimitReset->value))->toBeGreaterThan(0)
        ->and((int) $response->headers->get(HttpHeader::RateLimitReset->value))->toBeLessThanOrEqual(60)
        ->and($response->headers->get(HttpHeader::XRateLimitLimit->value))->toBe('10')
        ->and($response->headers->get(HttpHeader::XRateLimitRemaining->value))->toBe('9');

    for ($i = 0; $i < 9; $i++) {
        $this->post(Web::login->value, []);
    }

    $exhausted = $this->post(Web::login->value, []);

    expect($exhausted->getStatusCode())->toBe(429)
        ->and($exhausted->headers->get(HttpHeader::RateLimitRemaining->value))->toBe('0')
        ->and($exhausted->headers->get(HttpHeader::RateLimitPolicy->value))->toBe('10;w=60')
        ->and((int) $exhausted->headers->get(HttpHeader::RetryAfter->value))->toBeGreaterThan(0)
        ->and($exhausted->headers->get(HttpHeader::RateLimitReset->value))
        ->toBe($exhausted->headers->get(HttpHeader::RetryAfter->value));
});

test('the tightest of several ceilings is advertised, and an unthrottled route advertises none', function (): void {
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

    $unthrottled = $this->getJson(ApiRoute::readme->value);

    expect($unthrottled->headers->get(HttpHeader::RateLimitLimit->value))->toBeNull()
        ->and($unthrottled->headers->get(HttpHeader::RateLimitPolicy->value))->toBeNull();
});

beforeEach(function (): void {
    RateLimiter::clear('');
    cache()->flush();
});
