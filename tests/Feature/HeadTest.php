<?php

use App\Helpers\SessionKey;
use App\Helpers\Theme;
use App\Models\User;
use App\Routes\Auth;
use App\Routes\Web;
use Illuminate\Support\Facades\Config;

test('the layout renders the site defaults, a colour per theme, and the site verification', function (): void {
    $name = Config::string('app.name');

    Config::set('microsoft.content_id', 'CONTENT-ID');

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1.0">', false)
        ->assertSee('<meta name="robots" content="all">', false)
        ->assertSee("<meta property=\"og:site_name\" content=\"$name\">", false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('content="'.Theme::light->color().'" media="(prefers-color-scheme: light)"', false)
        ->assertSee('content="'.Theme::dark->color().'" media="(prefers-color-scheme: dark)"', false)
        ->assertSee('<meta name="msvalidate.01" content="CONTENT-ID">', false);

    Config::set('microsoft.content_id', null);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('msvalidate.01');
});

test('a title and description are page specific, fill the open graph tags, and hide settings from robots', function (): void {
    $name = Config::string('app.name');

    $this->get(Web::login->value)
        ->assertOk()
        ->assertSee("<title>Login - $name</title>", false)
        ->assertSee('<meta name="description" content="Sign in to your '.$name.' client account.">', false);

    $this->get(Web::register->value)
        ->assertOk()
        ->assertSee('<meta name="description" content="Create your account.">', false)
        ->assertSee("<meta property=\"og:title\" content=\"Register - $name\">", false)
        ->assertSee('<meta property="og:description" content="Create your account.">', false);

    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsAppearance->value)
        ->assertOk()
        ->assertSee("<title>Appearance - $name</title>", false)
        ->assertSee('<meta name="robots" content="none">', false);
});

test('the google tag sends a sign up event', function (): void {
    Config::set('google.tag_id', 'G-TEST');

    foreach (['Email', 'Google'] as $method) {
        session()->flash(SessionKey::sign_up_method->value, $method);

        $this->get(Web::home->value)
            ->assertOk()
            ->assertSee("gtag('event', 'sign_up', {", false)
            ->assertSee("method: '$method'", false);
    }
});

test('a page in the sitemap carries its own page as canonical and open graph url', function (): void {
    foreach ([
        Web::termsOfService->value,
        Web::privacyPolicy->value,
        Web::contact->value,
    ] as $route) {
        $content = (string) $this->get($route)->assertOk()->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonical);
        preg_match('/<meta property="og:url" content="([^"]+)"/', $content, $og);

        $expected = str_replace('http://', 'https://', rtrim(Config::string('app.url'), '/')).$route;

        expect($canonical[1] ?? null)->toBe($expected)
            ->and($og[1] ?? null)->toBe($canonical[1] ?? null);
    }
});
