<?php

use App\Helpers\SessionKey;
use App\Helpers\Theme;
use App\Models\User;
use App\Routes\Auth;
use App\Routes\Web;
use Illuminate\Support\Facades\Config;

test('the layout renders the site defaults', function (): void {
    $name = Config::string('app.name');

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1.0">', false)
        ->assertSee('<meta name="robots" content="all">', false)
        ->assertSee("<meta property=\"og:site_name\" content=\"$name\">", false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<link rel="canonical"', false);
});

test('the layout renders a theme color for each declared theme', function (): void {
    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('content="'.Theme::light->color().'" media="(prefers-color-scheme: light)"', false)
        ->assertSee('content="'.Theme::dark->color().'" media="(prefers-color-scheme: dark)"', false);
});

test('the google tag sends a sign up event', function (string $method): void {
    Config::set('google.tag_id', 'G-TEST');
    session()->flash(SessionKey::sign_up_method->value, $method);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee("gtag('event', 'sign_up', {", false)
        ->assertSee("method: '$method'", false);
})->with(['Email', 'Google']);

test('the layout verifies the site with Microsoft when a content id is configured', function (): void {
    Config::set('microsoft.content_id', 'CONTENT-ID');

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('<meta name="msvalidate.01" content="CONTENT-ID">', false);
});

test('the layout carries no Microsoft verification when none is configured', function (): void {
    Config::set('microsoft.content_id', null);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('msvalidate.01');
});

test('a page title is suffixed with the application name', function (): void {
    $name = Config::string('app.name');

    $this->get(Web::login->value)
        ->assertOk()
        ->assertSee("<title>Login - $name</title>", false);
});

test('the login page is indexable for search sitelinks', function (): void {
    $this->get(Web::login->value)
        ->assertOk()
        ->assertSee('<meta name="description" content="Sign in to your '.Config::string('app.name').' client account.">', false);
});

test('a page description replaces the default', function (): void {
    $this->get(Web::register->value)
        ->assertOk()
        ->assertSee('<meta name="description" content="Create your account.">', false);
});

test('the document title and description fill the open graph tags', function (): void {
    $name = Config::string('app.name');

    $this->get(Web::register->value)
        ->assertOk()
        ->assertSee("<meta property=\"og:title\" content=\"Register - $name\">", false)
        ->assertSee('<meta property="og:description" content="Create your account.">', false);
});

test('the settings pages are hidden from robots', function (): void {
    $name = Config::string('app.name');

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsAppearance->value)
        ->assertOk()
        ->assertSee("<title>Appearance - $name</title>", false)
        ->assertSee('<meta name="robots" content="none">', false);
});

test('pages in sitemap carry their own page as canonical and open graph url', function (string $route): void {
    $content = (string) $this->get($route)->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonical);
    preg_match('/<meta property="og:url" content="([^"]+)"/', $content, $og);

    $expected = str_replace('http://', 'https://', rtrim(Config::string('app.url'), '/')).$route;

    expect($canonical[1] ?? null)->toBe($expected)
        ->and($og[1] ?? null)->toBe($canonical[1] ?? null);
})->with([
    Web::termsOfService->value,
    Web::privacyPolicy->value,
    Web::contact->value,
]);
