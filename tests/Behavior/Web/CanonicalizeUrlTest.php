<?php

use App\Http\Middleware\CanonicalizeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('a request is redirected once to the canonical scheme and host, query and all', function (): void {
    $middleware = new CanonicalizeUrl;

    $redirect = static function (string $url, string $host, string $scheme) use ($middleware): RedirectResponse {
        $Request = Request::create($url, 'GET');
        $Request->server->set('HTTP_HOST', $host);
        $Request->server->set('REQUEST_SCHEME', $scheme);

        $Response = $middleware->handle($Request, static fn (Request $r) => new Response('pass'));

        expect($Response)->toBeInstanceOf(RedirectResponse::class);
        assert($Response instanceof RedirectResponse);
        expect($Response->getStatusCode())->toBe(301);

        return $Response;
    };

    app()->instance('env', 'production');

    expect($redirect('http://discoveryto.com/path', 'discoveryto.com', 'http')->getTargetUrl())
        ->toBe('https://discoveryto.com/path')
        ->and($redirect('http://www.discoveryto.com/path?query=1', 'www.discoveryto.com', 'http')->getTargetUrl())
        ->toBe('https://discoveryto.com/path?query=1')
        ->and($redirect('https://www.discoveryto.com/path', 'www.discoveryto.com', 'https')->getTargetUrl())
        ->toBe('https://discoveryto.com/path')
        ->and($redirect('https://www.discoveryto.com/api/users?sort=name&limit=10', 'www.discoveryto.com', 'https')->getTargetUrl())
        ->toBe('https://discoveryto.com/api/users?sort=name&limit=10')
        // Fragments are client side and never reach the server, so a redirect omits them.
        ->and($redirect('https://www.discoveryto.com/docs', 'www.discoveryto.com', 'https')->getTargetUrl())
        ->toBe('https://discoveryto.com/docs');
});

test('a canonical url, and any url in local development, passes through', function (): void {
    $middleware = new CanonicalizeUrl;

    $passthrough = static function (string $url, string $host, string $scheme) use ($middleware): Response {
        $Request = Request::create($url, 'GET');
        $Request->server->set('HTTP_HOST', $host);
        $Request->server->set('REQUEST_SCHEME', $scheme);

        $Response = $middleware->handle($Request, static fn (Request $r) => new Response('pass through'));

        expect($Response)->toBeInstanceOf(Response::class);
        assert($Response instanceof Response);

        return $Response;
    };

    app()->instance('env', 'production');

    expect($passthrough('https://discoveryto.com/path', 'discoveryto.com', 'https')->getContent())
        ->toBe('pass through');

    app()->instance('env', 'local');

    expect($passthrough('http://localhost:8080/path', 'localhost:8080', 'http')->getContent())
        ->toBe('pass through');
});
