<?php

use App\Helpers\CacheKey;
use App\Helpers\HttpHeader;
use App\Http\Middleware\CanonicalizeUrl;
use App\Modules\Sitemap\Sitemap;
use App\Routes\ApiRoute;
use App\Routes\MiddlewareTag;
use App\Routes\Web;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/** @return list<string> */
function sitemapLocations(string $xml): array
{
    preg_match_all('#<loc>(.*?)</loc>#', $xml, $matches);

    return $matches[1];
}

/** @return list<string> */
function sitemapModifications(string $xml): array
{
    preg_match_all('#<lastmod>(.*?)</lastmod>#', $xml, $matches);

    return $matches[1];
}

test('what a crawler reads is published whole, dated, within the protocol cap, canonical, and never throttled away', function (): void {
    $TestResponse = $this->get(Web::robots->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee((string) file_get_contents(resource_path(CacheKey::robots->value)), false);

    expect($TestResponse->getContent())->toStartWith('User-agent: ');

    $this->forgetCredentials();

    $TestResponse = $this->get(Web::llms->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee((string) file_get_contents(resource_path(CacheKey::llms->value)), false);

    expect($TestResponse->getContent())->toStartWith('# ');

    $this->forgetCredentials();

    config(['microsoft.content_id' => 'configured-content-id']);

    $this->get(Web::bingSiteAuth->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<user>configured-content-id</user>', escape: false);

    $this->forgetCredentials();

    $expected = Sitemap::pages()
            |> array_keys(...)
            |> (static fn ($x) => array_map(static fn (int $page): string => url(Web::sitemapPage->url(['page' => $page])), $x));

    $TestResponse = $this->get(Web::sitemap->value)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertDontSee('<urlset', false);

    $index = (string) $TestResponse->getContent();

    expect($expected)->not->toBeEmpty()
        ->and(sitemapLocations($index))->toBe($expected);

    foreach ($expected as $loc) {
        $this->get($loc)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
    }

    $this->get(Web::robots->value)
        ->assertOk()
        ->assertSee('Sitemap: '.url(Web::sitemap->url()), false);

    $expected = array_map(static fn (Web $page): string => Sitemap::location($page), Web::sitemap());

    $index = (string) $this->get(Web::sitemap->value)->getContent();
    $listed = [];
    $times = sitemapModifications($index);

    foreach (sitemapLocations($index) as $sitemap) {
        $page = (string) $this->get($sitemap)->getContent();
        $listed = [...$listed, ...sitemapLocations($page)];
        $times = [...$times, ...sitemapModifications($page)];
    }

    expect($expected)->not->toBeEmpty()
        ->and($listed)->toBe($expected)
        ->and($times)->not->toBeEmpty()
        // The root is advertised in the spelling the page itself declares canonical. The
        // generator drops a path that is only a separator, so without this the sitemap and the
        // page disagree about the one address they both mean, and the crawler reports whichever
        // it discarded as a page it was sent to and told not to index.
        ->and(Sitemap::location(Web::home))->toEndWith('/')
        ->and($listed)->toContain(Sitemap::location(Web::home))
        // An entrance to authentication is public without being a page worth listing: it is
        // held out of the sitemap and out of the index together, because a sitemap entry is a
        // request to index and the two claims cannot disagree.
        ->and($listed)->not->toContain(Sitemap::location(Web::login))
        ->and($listed)->not->toContain(Sitemap::location(Web::register))
        ->and(Web::sitemap())->not->toContain(Web::login)
        ->and(Web::sitemap())->not->toContain(Web::register);

    foreach ($listed as $loc) {
        $this->get($loc)->assertOk();
    }

    foreach ($times as $time) {
        expect(DateTimeImmutable::createFromFormat(DATE_W3C, $time))->toBeInstanceOf(DateTimeImmutable::class);
    }

    $this->get(Web::sitemapPage->url(['page' => count(Sitemap::pages()) + 1]))->assertNotFound();
    $this->get(Web::sitemapPage->url(['page' => 0]))->assertNotFound();

    expect(Sitemap::urlLimit)->toBe(50_000)
        ->and(Sitemap::pages())->not->toBeEmpty();

    foreach (Sitemap::pages() as $cases) {
        expect(count($cases))->toBeLessThanOrEqual(Sitemap::urlLimit);
    }

    for ($i = 0; $i < 3; $i++) {
        $index = $this->get(Web::sitemap->value)->assertOk();

        foreach (sitemapLocations((string) $index->getContent()) as $sitemap) {
            $this->get($sitemap)->assertOk();
        }
    }

    $this->forgetCredentials();

    RateLimiter::clear('');
    cache()->flush();

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

    $this->forgetCredentials();

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
        ->and($redirect('https://www.discoveryto.com/docs', 'www.discoveryto.com', 'https')->getTargetUrl())
        ->toBe('https://discoveryto.com/docs');

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

    $secure = $passthrough('https://discoveryto.com/path', 'discoveryto.com', 'https');

    expect($secure->getContent())->toBe('pass through')
        ->and($secure->headers->get(HttpHeader::StrictTransportSecurity->value))
        ->toBe(CanonicalizeUrl::transportPolicy)
        ->and($redirect('http://discoveryto.com/path', 'discoveryto.com', 'http')
            ->headers->get(HttpHeader::StrictTransportSecurity->value))->toBeNull();

    app()->instance('env', 'local');

    $insecure = $passthrough(Config::string('app.url').'/path', 'localhost:8080', 'http');

    expect($insecure->getContent())->toBe('pass through')
        ->and($insecure->headers->get(HttpHeader::StrictTransportSecurity->value))->toBeNull();
});
