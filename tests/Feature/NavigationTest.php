<?php

use App\AppConfig;
use App\Helpers\Role;
use App\Helpers\SessionKey;
use App\Helpers\SvgName;
use App\Helpers\Theme;
use App\Models\User;
use App\Routes\Admin;
use App\Routes\AdminLink;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\MiddlewareTag;
use App\Routes\OrganizationRoute;
use App\Routes\RouteIndex;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Users;
use App\View\DataModels\DocsNav;
use App\View\DataModels\Main;
use App\View\DataModels\Nav;
use App\View\DataModels\NavItem;
use App\View\DataModels\OrganizationNav;
use App\View\DataModels\OrganizationSwitcher;
use App\View\DataModels\SettingsNav;
use App\View\DataModels\Svg;
use App\View\DataModels\Topnav;
use App\View\DataModels\UserMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Head\Facades\Head;
use Tests\Fixtures\RouteIndexStub;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @return list<string> */
function markdownFiles(string $base): array
{
    $files = glob($base.'/*.md') ?: [];

    $Directory = new RecursiveDirectoryIterator($base.'/docs', FilesystemIterator::SKIP_DOTS);

    $Iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            $Directory,
            static fn (SplFileInfo $File): bool => $File->getFilename() !== 'repos',
        )
    );

    foreach ($Iterator as $File) {
        if ($File instanceof SplFileInfo && $File->getExtension() === 'md') {
            $files[] = $File->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** @return array<string, int> */
function taggedOrders(): array
{
    $orders = [];

    foreach (AppConfig::routeIndexes() as $enum) {
        foreach (AdminLink::links($enum) as $link) {
            $orders[$link[AdminLink::url]] = $link[AdminLink::order];
        }
    }

    return $orders;
}

test('every rail, dropdown and head is built from route cases, active on its own path and shown where the page allows', function (): void {
    expect(AppConfig::routeIndexes())
        ->toContain(Admin::class, ApiRoute::class, Auth::class, OrganizationRoute::class, Web::class)
        ->and(AppConfig::routeIndexes())
        ->not->toContain(MiddlewareTag::class, RouteIndexStub::class)
        ->and(AppConfig::routeIndexes())->toBe(array_column(RouteIndex::cases(), 'value'));

    foreach (AppConfig::routeIndexes() as $enum) {
        expect(enum_exists($enum))->toBeTrue()
            ->and(new ReflectionEnum($enum)->getBackingType()?->getName())->toBe('string');
    }

    $route = ['id' => '1', 'hash' => 'abc'];

    expect(Web::login->isActive(Request::create(Web::login->value)))->toBeTrue()
        ->and(Web::login->isActive(Request::create(Web::login->value.'/callback')))->toBeTrue()
        ->and(Web::login->isActive(Request::create(Web::register->value)))->toBeFalse()
        ->and(ApiRoute::user->isExact(Request::create(ApiRoute::user->value)))->toBeTrue()
        ->and(ApiRoute::user->isExact(Request::create(ApiRoute::user->value.'/callback')))->toBeFalse()
        ->and(Auth::verificationVerify->isExact(Request::create('/email/verify/1/abc'), $route))->toBeTrue()
        ->and(Auth::verificationVerify->isActive(Request::create('/email/verify/1/abc'), $route))->toBeTrue();

    $NavItem = NavItem::from([
        NavItem::label => 'Home',
        NavItem::icon => SvgName::home,
        NavItem::route => Web::home,
    ]);

    expect($NavItem->label)->toBe('Home')
        ->and($NavItem->icon)->toBe(SvgName::home)
        ->and($NavItem->route)->toBe(Web::home)
        ->and($NavItem->url())->toBe(Web::home->url())
        ->and($NavItem->parameters)->toBeEmpty()
        ->and(static fn () => NavItem::from([NavItem::label => 'Home', NavItem::icon => SvgName::home]))
        ->toThrow(PropertyRequiredException::class);

    $Parameterised = NavItem::from([
        NavItem::label => 'Overview',
        NavItem::icon => SvgName::home,
        NavItem::route => OrganizationRoute::index,
        NavItem::parameters => [OrganizationRoute::organizationParameter => 'acme'],
    ]);

    app()->instance('request', Request::create('/o/acme'));

    expect($Parameterised->url())->toBe('/o/acme')
        ->and($Parameterised->active())->toBeTrue()
        ->and($Parameterised->url())->not->toContain('{');

    app()->instance('request', Request::create('/o/globex'));

    expect($Parameterised->active())->toBeFalse();

    $Nested = NavItem::from([
        NavItem::label => 'Members',
        NavItem::icon => SvgName::user,
        NavItem::route => OrganizationRoute::members,
        NavItem::parameters => [OrganizationRoute::organizationParameter => 'acme'],
        NavItem::nested => true,
    ]);

    app()->instance('request', Request::create('/o/acme/members/01hzz'));

    expect($Nested->url())->toBe('/o/acme/members')
        ->and($Nested->active())->toBeTrue();

    $Svg = Svg::from($NavItem->svg());

    expect($Svg->name)->toBe(SvgName::home)
        ->and($Svg->classname)->toBe('h-4 w-4 opacity-70');

    $NavItem = NavItem::from([
        NavItem::label => 'Home',
        NavItem::icon => SvgName::home,
        NavItem::route => Web::home,
    ]);

    $this->get(Web::home->value)->assertOk();

    expect($NavItem->nested)->toBeFalse()
        ->and($NavItem->active())->toBeTrue();

    $this->get(Web::contact->value)->assertOk();

    expect($NavItem->active())->toBeFalse();

    $Nested = NavItem::from([
        NavItem::label => 'Credentials',
        NavItem::icon => SvgName::command_line,
        NavItem::route => Auth::settingsCredentials,
        NavItem::nested => true,
    ]);

    app()->instance('request', Request::create(Auth::settingsCredential->url([Auth::credentialParameter => 'abc'])));

    expect($Nested->active())->toBeTrue();

    app()->instance('request', Request::create(Auth::settingsProfile->value));

    expect($Nested->active())->toBeFalse();

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('lg:pl-56');

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('menu-active');

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertDontSee('aria-label="Primary"', false)
        ->assertSee('aria-label="Settings"', false);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('lg:pl-56')
        ->assertSee('aria-label="Settings"', false);

    $this->actingAs($User)
        ->get(Auth::settingsAppearance->value)
        ->assertOk()
        ->assertSee('menu-active');

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('aria-label="Settings"', false);

    foreach (
        [
            'Credentials' => Auth::settingsCredential->url([Auth::credentialParameter => 'abc']),
            'Sessions' => Auth::settingsSession->url([Auth::sessionParameter => 'abc']),
        ] as $label => $url
    ) {
        app()->instance('request', Request::create($url));

        $active = [];

        foreach (SettingsNav::items() as $NavItem) {
            $active[$NavItem->label] = $NavItem->active();
        }

        expect($active[$label])->toBeTrue()
            ->and($active['Profile'])->toBeFalse();
    }

    $this->get(Web::docsApi->value)
        ->assertOk()
        ->assertSee('aria-label="Documentation"', false)
        ->assertSee('lg:pl-56');

    foreach ([Web::docs, Web::docsApi, Web::docsMcp] as $Web) {
        app()->instance('request', Request::create($Web->value));

        expect(DocsNav::visible())->toBeTrue();
    }

    app()->instance('request', Request::create(Web::home->value));

    expect(DocsNav::visible())->toBeFalse();

    foreach (
        [
            ['John Doe', 'JD'],
            ['John Quincy Doe', 'JD'],
            ['Prince', 'P'],
            ['  john   doe  ', 'JD'],
            ['', '?'],
            ['   ', '?'],
        ] as [$name, $initials]
    ) {
        expect(UserMenu::from([UserMenu::name => $name])->initials())->toBe($initials);
    }

    // The address is this segment's alone, so every fetch of its avatar is one
    // this segment caused: rendering the topnav twice is allowed exactly one.
    $this->flushSession();
    $gravatar = 'https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=80&d=404&r=g';
    $Gravatared = User::factory()->createOne([
        Users::email->value => 'MyEmailAddress@example.com',
    ]);

    $this->actingAs($Gravatared)->get(Web::home->value)->assertOk();
    $this->get(Web::home->value)->assertOk();

    expect(Http::recorded(static fn ($Request): bool => $Request->url() === $gravatar))->toHaveCount(1)
        ->and(session(SessionKey::user_picture->value))->toBe('data:image/jpeg;base64,'.base64_encode('gravatar'))
        ->and(UserMenu::items())->toHaveCount(2)
        ->and(UserMenu::items()[0]->route)->toBe(Auth::settingsProfile)
        ->and(UserMenu::items()[1]->route)->toBe(Web::logout);

    $User = User::factory()->createOne([
        Users::name->value => 'John Doe',
        Users::email->value => 'john@example.com',
    ]);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee(Admin::index->value)
        ->assertSee('data:image/jpeg;base64,'.base64_encode('gravatar'))
        ->assertSee('John Doe')
        ->assertSee('john@example.com')
        ->assertSee(Auth::settingsProfile->value)
        ->assertSee(Web::logout->value);

    $User->assignRole(Role::admin->value);
    $this->actingAs($User);

    expect(UserMenu::items())->toHaveCount(3)
        ->and(UserMenu::items()[0]->route)->toBe(Admin::index);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee(Admin::index->value);

    $this->actingAs($User)
        ->withSession([SessionKey::user_picture->value => 'https://example.com/avatar.jpg'])
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('https://example.com/avatar.jpg')
        ->assertDontSee('JD');

    Config::set('services.google.client_id', 'client-id.apps.googleusercontent.com');

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-google-one-tap', false);

    $this->get(Web::logout->value);
    $this->flushSession();

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee(Web::login->value)
        ->assertSee(Web::googleOneTap->value)
        ->assertSee('data-google-one-tap', false)
        ->assertSee('client-id.apps.googleusercontent.com')
        ->assertDontSee(Web::logout->value);

    $Main = Main::from([]);

    expect($Main->classnames)->toBeNull()
        ->and($Main->theme)->toBeNull()
        ->and($Main->nav)->toBeNull()
        ->and($Main->topnav())->toBe([Topnav::nav => null])
        ->and(Main::from([Main::classnames => 'bg-base-200'])->classnames)->toBe('bg-base-200')
        ->and(Main::from([Main::nav => Nav::admin])->nav)->toBe(Nav::admin)
        ->and(Main::from([Main::nav => Nav::admin])->topnav())->toBe([Topnav::nav => Nav::admin]);

    $this->actingAs(User::factory()->createOne([Users::theme->value => Theme::dark]));

    expect(Main::from([])->theme)->toBe(Theme::dark->value);

    $this->actingAs(User::factory()->createOne([Users::theme->value => Theme::auto]));

    expect(Main::from([])->theme)->toBeNull();

    // The head is written to once per request and read once, so a page rendered
    // earlier in this test is still holding the pen until it is put down.
    $this->get(Web::logout->value);
    $this->flushSession();
    Head::flush();

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

    Config::set('google.tag_id', 'G-TEST');

    foreach (['Email', 'Google'] as $method) {
        session()->flash(SessionKey::sign_up_method->value, $method);

        $this->get(Web::home->value)
            ->assertOk()
            ->assertSee("gtag('event', 'sign_up', {", false)
            ->assertSee("method: '$method'", false);
    }

    foreach (
        [
            Web::termsOfService->value,
            Web::privacyPolicy->value,
            Web::contact->value,
        ] as $route
    ) {
        $content = (string) $this->get($route)->assertOk()->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonical);
        preg_match('/<meta property="og:url" content="([^"]+)"/', $content, $og);

        $expected = str_replace('http://', 'https://', rtrim(Config::string('app.url'), '/')).$route;

        expect($canonical[1] ?? null)->toBe($expected)
            ->and($og[1] ?? null)->toBe($canonical[1] ?? null);
    }

    $this->get(Web::logout->value);
    $this->flushSession();

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('href="'.Web::login->value.'"', false)
        ->assertSee('href="'.Web::contact->value.'"', false)
        ->assertSee('>Login</span>', false)
        ->assertSee('>Contact</span>', false)
        ->assertSee(Config::string('app.name'))
        ->assertSee(Config::string('brand.logo_title'));

    $this->actingAs(User::factory()->createOne())
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-home-login', false)
        ->assertSee('href="'.Web::contact->value.'"', false);

    config()->set('brand.attribution', true);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('data-digitalforte-link="header_lockup"', false)
        ->assertSee('data-digitalforte-link="footer_attribution"', false)
        ->assertSee('text-digitalforte-primary', false)
        ->assertSee('text-digitalforte-secondary', false)
        ->assertSee('digitalforte_referral_click');

    config()->set('brand.attribution', false);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-digitalforte-link', false)
        ->assertDontSee('digitalforte_referral_click')
        ->assertSee(Config::string('app.name'));
});

test('organization nav and switcher coverage', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [
        Organizations::name->value => 'Test Corp',
        Organizations::slug->value => 'test-corp',
    ]);

    app()->instance('request', Request::create('/o/test-corp'));

    $this->actingAs($User)->get(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'test-corp']))->assertOk();

    expect(OrganizationNav::visible())->toBeTrue()
        ->and(OrganizationNav::label())->toBe('Organization')
        ->and(OrganizationNav::items())->toHaveCount(3);

    $Switcher = OrganizationSwitcher::current();
    assert($Switcher instanceof OrganizationSwitcher);

    expect($Switcher->name)->toBe('Test Corp')
        ->and($Switcher->slug)->toBe('test-corp')
        ->and($Switcher->initials())->toBe('TC')
        ->and($Switcher->iconUrl())->toBeNull()
        ->and($Switcher->sections())->toHaveCount(1)
        ->and(OrganizationNav::items()[0]->label)->toBe('Overview')
        ->and(OrganizationNav::items()[1]->label)->toBe('Connections')
        ->and(OrganizationNav::items()[2]->label)->toBe('Members');

    app()->instance('request', Request::create(Web::home->value));

    expect(OrganizationNav::visible())->toBeFalse()
        ->and(OrganizationSwitcher::current())->toBeNull()
        ->and(OrganizationNav::items())->toBeEmpty();
});
