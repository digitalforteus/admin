<?php

use App\Modules\Sitemap\Sitemap;
use App\Routes\ExcludeFromSitemap;
use App\Routes\Web;

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

test('the root document is a sitemap index that names every page, each served as xml', function (): void {
    $expected = array_map(
        static fn (int $page): string => url(Web::sitemapPage->url(['page' => $page])),
        array_keys(Sitemap::pages()),
    );

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
});

// The referenced documents are the whole of the sitemap, so together they carry exactly the
// advertised paths, in order — a page missing from one of them leaves the site's index
// without it while the root document still looks complete. The attribute is a claim about
// each page; reaching every one of them is that claim being checked, and a route that stops
// being public, or gains a parameter, has to be excluded or this fails. A modification time
// a crawler cannot parse is worse than declaring none, and one that moves on its own is why
// it is read off a file rather than a clock.
test('the pages list every route not marked '.ExcludeFromSitemap::class.', each reachable and dated', function (): void {
    $expected = array_map(static fn (Web $page): string => url($page->url()), Web::sitemap());

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
        ->and($times)->not->toBeEmpty();

    foreach ($listed as $loc) {
        $this->get($loc)->assertOk();
    }

    foreach ($times as $time) {
        expect(DateTimeImmutable::createFromFormat(DATE_W3C, $time))->toBeInstanceOf(DateTimeImmutable::class);
    }
});

// Numbering is the whole of addressing a page, so a number no page answers to is not a
// document with nothing in it: an empty one invites a crawler to drop what it already has.
// The cap is the protocol's, not a preference, and it is what makes splitting necessary at
// all: a document over it is rejected whole by the crawler that reads it.
test('a page number outside the range is not found, and no page carries more paths than the protocol allows', function (): void {
    $this->get(Web::sitemapPage->url(['page' => count(Sitemap::pages()) + 1]))->assertNotFound();
    $this->get(Web::sitemapPage->url(['page' => 0]))->assertNotFound();

    expect(Sitemap::urlLimit)->toBe(50_000)
        ->and(Sitemap::pages())->not->toBeEmpty();

    foreach (Sitemap::pages() as $cases) {
        expect(count($cases))->toBeLessThanOrEqual(Sitemap::urlLimit);
    }
});

// A crawler reads the index and then every document it names, so a limit shared across
// them is spent on the first visit — and a refused fetch is read as the sitemap being
// gone rather than as being asked to slow down, which unpublishes the site quietly.
test('reading the index and every page it names is not rate limited', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $index = $this->get(Web::sitemap->value)->assertOk();

        foreach (sitemapLocations((string) $index->getContent()) as $sitemap) {
            $this->get($sitemap)->assertOk();
        }
    }
});
