<?php

use App\Http\Middleware\CanonicalizeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('http requests redirect to https in production', function (): void {
    app()->instance('env', 'production');

    $middleware = new CanonicalizeUrl;
    $request = Request::create('http://discoveryto.com/path', 'GET');
    $request->server->set('REQUEST_SCHEME', 'http');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    assert($response instanceof RedirectResponse);
    expect($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toBe('https://discoveryto.com/path');
});

test('http requests pass through in local development', function (): void {
    app()->instance('env', 'local');

    $middleware = new CanonicalizeUrl;
    $request = Request::create('http://localhost:8080/path', 'GET');
    $request->server->set('REQUEST_SCHEME', 'http');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass through'));

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('pass through');
});

test('www subdomain redirects to non-www', function (): void {
    $middleware = new CanonicalizeUrl;
    $request = Request::create('https://www.discoveryto.com/path', 'GET');
    $request->server->set('HTTP_HOST', 'www.discoveryto.com');
    $request->server->set('REQUEST_SCHEME', 'https');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    assert($response instanceof RedirectResponse);
    expect($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toBe('https://discoveryto.com/path');
});

test('http www combination redirects to https non-www', function (): void {
    app()->instance('env', 'production');

    $middleware = new CanonicalizeUrl;
    $request = Request::create('http://www.discoveryto.com/path?query=1', 'GET');
    $request->server->set('HTTP_HOST', 'www.discoveryto.com');
    $request->server->set('REQUEST_SCHEME', 'http');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    assert($response instanceof RedirectResponse);
    expect($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toBe('https://discoveryto.com/path?query=1');
});

test('already canonical urls pass through', function (): void {
    $middleware = new CanonicalizeUrl;
    $request = Request::create('https://discoveryto.com/path', 'GET');
    $request->server->set('HTTP_HOST', 'discoveryto.com');
    $request->server->set('REQUEST_SCHEME', 'https');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass through'));

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('pass through');
});

test('preserves query parameters in redirect', function (): void {
    $middleware = new CanonicalizeUrl;
    $request = Request::create('https://www.discoveryto.com/api/users?sort=name&limit=10', 'GET');
    $request->server->set('HTTP_HOST', 'www.discoveryto.com');
    $request->server->set('REQUEST_SCHEME', 'https');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    assert($response instanceof RedirectResponse);
    expect($response->getTargetUrl())->toBe('https://discoveryto.com/api/users?sort=name&limit=10');
});

test('fragments are not sent to server so are not redirected', function (): void {
    // Fragments (#) are client-side only and never sent to the server,
    // so they won't appear in request URI. This test verifies that redirects
    // correctly omit fragments (which is correct behavior).
    $middleware = new CanonicalizeUrl;
    $request = Request::create('https://www.discoveryto.com/docs', 'GET');
    $request->server->set('HTTP_HOST', 'www.discoveryto.com');
    $request->server->set('REQUEST_SCHEME', 'https');

    $response = $middleware->handle($request, fn (Request $r) => new Response('pass'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    assert($response instanceof RedirectResponse);
    expect($response->getTargetUrl())->toBe('https://discoveryto.com/docs');
});
