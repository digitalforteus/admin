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
use App\Routes\RouteIndex;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use App\View\DataModels\AdminNav;
use App\View\DataModels\DocsNav;
use App\View\DataModels\LeftNav;
use App\View\DataModels\Main;
use App\View\DataModels\NavItem;
use App\View\DataModels\SettingsNav;
use App\View\DataModels\Svg;
use App\View\DataModels\Topnav;
use App\View\DataModels\UserMenu;
use App\View\ViewDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Head\Facades\Head;
use Tests\Fixtures\RouteIndexStub;
use Zerotoprod\DataModel\PropertyRequiredException;

// A case of the registry is the whole of registering an index: an enum it does not name
// is not one, wherever that enum lives. The registry is read rather than discovered, so
// the order it declares its cases in is the order the indexes come back in.

// Docs are what an agent reads before touching an endpoint, and a link to a
// file that does not exist is indistinguishable from a file it failed to find.
// Cheaper to fail the gate than to have the reader re-search.

/**
 * The markdown this repo owns: the docs, and the instruction files at the root.
 * `vendor` and `node_modules` are somebody else's to keep honest, and so is
 * `docs/repos` — mirrored upstream docs, gitignored, written against link
 * rewriters this repo does not run. Including them would make the gate fail or
 * pass on whether a contributor happens to have synced a mirror.
 *
 * @return list<string>
 */
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

/**
 * The order every tagged case asked for, keyed by the url it renders. Read off the
 * indexes rather than restated, so tagging a case is the only place an order is written.
 *
 * @return array<string, int>
 */
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
        ->toContain(Admin::class, ApiRoute::class, Auth::class, Web::class)
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
        ->and(static fn () => NavItem::from([NavItem::label => 'Home', NavItem::icon => SvgName::home]))
        ->toThrow(PropertyRequiredException::class);

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

    expect($Nested->active())->toBeFalse()
        ->and(LeftNav::items())->toHaveCount(2)
        ->and(LeftNav::items()[0]->route)->toBe(Web::home)
        ->and(collect(LeftNav::items())->pluck('route')->all())->toContain(Web::contact);

    foreach (LeftNav::cases() as $LeftNav) {
        expect($LeftNav->item())->toBeInstanceOf(NavItem::class);
    }

    foreach ([
        [null, 'Left navigation cases must describe a navigation item.'],
        [[Web::home], 'Left navigation attributes must be named.'],
    ] as [$item, $message]) {
        expect(static fn (): mixed => new ReflectionMethod(LeftNav::class, 'attributes')->invoke(null, $item))
            ->toThrow(LogicException::class, $message);
    }

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

    $items = SettingsNav::items();

    expect($items[0]->label)->toBe('Profile')
        ->and($items[0]->route)->toBe(Auth::settingsProfile)
        ->and(collect($items)->pluck('route')->all())
        ->toBe([
            Auth::settingsProfile,
            Auth::settingsAppearance,
            Auth::settingsSecurity,
            Auth::settingsCredentials,
            Auth::settingsSessions,
        ]);

    foreach (SettingsNav::cases() as $SettingsNav) {
        expect($SettingsNav->item())->toBeInstanceOf(NavItem::class);
    }

    foreach ($items as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }

    foreach ([
        [null, 'Settings navigation cases must describe a navigation item.'],
        [[Auth::settingsProfile], 'Settings navigation attributes must be named.'],
    ] as [$item, $message]) {
        expect(static fn (): mixed => new ReflectionMethod(SettingsNav::class, 'attributes')->invoke(null, $item))
            ->toThrow(LogicException::class, $message);
    }

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

    foreach ([
        'Credentials' => Auth::settingsCredential->url([Auth::credentialParameter => 'abc']),
        'Sessions' => Auth::settingsSession->url([Auth::sessionParameter => 'abc']),
    ] as $label => $url) {
        app()->instance('request', Request::create($url));

        $active = [];

        foreach (SettingsNav::items() as $NavItem) {
            $active[$NavItem->label] = $NavItem->active();
        }

        expect($active[$label])->toBeTrue()
            ->and($active['Profile'])->toBeFalse();
    }

    $items = DocsNav::items();

    expect($items[0]->label)->toBe('API')
        ->and($items[0]->route)->toBe(Web::docsApi)
        ->and(collect($items)->pluck('route')->all())->toContain(Web::docsMcp);

    foreach (DocsNav::cases() as $DocsNav) {
        expect($DocsNav->item())->toBeInstanceOf(NavItem::class);
    }

    foreach ($items as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }

    foreach ([
        [null, 'Documentation navigation cases must describe a navigation item.'],
        [[Web::docsApi], 'Documentation navigation attributes must be named.'],
    ] as [$item, $message]) {
        expect(static fn (): mixed => new ReflectionMethod(DocsNav::class, 'attributes')->invoke(null, $item))
            ->toThrow(LogicException::class, $message);
    }

    foreach ([Web::docs, Web::docsApi, Web::docsMcp] as $Web) {
        app()->instance('request', Request::create($Web->value));

        expect(DocsNav::visible())->toBeTrue();
    }

    app()->instance('request', Request::create(Web::home->value));

    expect(DocsNav::visible())->toBeFalse();

    $broken = [];

    foreach (markdownFiles(base_path()) as $file) {
        $contents = (string) file_get_contents($file);

        preg_match_all('/]\(([^)#]+?)(?:#[^)]*)?\)/', $contents, $matches);

        foreach ($matches[1] as $target) {
            if (preg_match('#^(https?:|mailto:|/)#', $target) === 1) {
                continue;
            }

            if (! file_exists(dirname($file).'/'.$target)) {
                $broken[] = str_replace(base_path().'/', '', $file).' -> '.$target;
            }
        }
    }

    expect($broken)->toBeEmpty("Markdown links pointing at nothing:\n  - ".implode("\n  - ", $broken));

    $items = AdminNav::items();

    expect($items[0]->label)->toBe('Dashboard')
        ->and($items[0]->route)->toBe(Admin::index)
        ->and(collect($items)->pluck('route')->all())->toContain(Admin::users)
        ->and(collect($items)->pluck('route')->all())->toContain(Admin::sessions);

    foreach ($items as $NavItem) {
        expect(ViewDirectory::svg->has($NavItem->icon))->toBeTrue();
    }

    $attributes = new ReflectionClass(AdminLink::class)->getAttributes(Attribute::class);

    expect($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS_CONSTANT)
        ->and(array_column(AdminLink::routes(), AdminLink::url))->toContain(
            Web::robots->value,
            Web::llms->value,
            Web::sitemap->value,
            Web::openapi->value,
            ApiRoute::readme->value,
            Admin::openapi->value,
        );

    // Every tagged case is listed once, wherever it was tagged, and an order is what moves it
    // up the page: the sequence of orders the page renders never descends. The argument is
    // optional, and an absent order is not a first one: the case that gives none sorts behind
    // every case that does.
    $orders = taggedOrders();
    $listed = array_column(AdminLink::routes(), AdminLink::url);

    $sequence = array_map(static fn (string $url): int => $orders[$url], $listed);
    $ascending = $sequence;
    sort($ascending);

    expect($listed)->toEqualCanonicalizing(array_keys($orders))
        ->and($listed)->toHaveSameSize($orders)
        ->and($sequence)->toBe($ascending)
        ->and(new AdminLink()->order)->toBeNull()
        ->and(AdminLink::links(RouteIndexStub::class))->toBe([[
            AdminLink::order => PHP_INT_MAX,
            AdminLink::name => RouteIndexStub::bare->name,
            AdminLink::url => RouteIndexStub::bare->value,
        ]]);

    // An enum reports what it holds, in the order it declares it. Sorting is the job of the
    // query where every index's links meet. A case tagged in an enum the registry does not
    // name is not the application's routing, so the page does not display it.
    $tagged = array_column(AdminLink::links(Web::class), AdminLink::name);

    $declared = array_values(array_filter(
        array_map(static fn (Web $Case): string => $Case->name, Web::cases()),
        static fn (string $name): bool => in_array($name, $tagged, true),
    ));

    expect($tagged)->not->toBeEmpty()
        ->and($tagged)->toBe($declared)
        ->and(AdminLink::links(Auth::class))->toBeEmpty()
        ->and(AdminLink::links(RouteIndexStub::class))->not->toBeEmpty()
        ->and(array_column(AdminLink::routes(), AdminLink::url))
        ->not->toContain(RouteIndexStub::bare->value);

    $None = Topnav::from([]);

    expect($None->leftNav)->toBeFalse()
        ->and($None->adminNav)->toBeFalse()
        ->and($None->settingsNav)->toBeFalse()
        ->and($None->nav())->toBeFalse();

    $Left = Topnav::from([Topnav::leftNav => true]);

    expect($Left->nav())->toBeTrue()
        ->and($Left->items())->toEqual(LeftNav::items());

    // The admin rail wins wherever both are standing.
    expect(Topnav::from([Topnav::leftNav => true, Topnav::adminNav => true])->items())
        ->toEqual(AdminNav::items());

    $Settings = Topnav::from([Topnav::settingsNav => true]);

    expect($Settings->nav())->toBeTrue()
        ->and($Settings->items())->toEqual(SettingsNav::items());

    foreach ([
        ['John Doe', 'JD'],
        ['John Quincy Doe', 'JD'],
        ['Prince', 'P'],
        ['  john   doe  ', 'JD'],
        ['', '?'],
        ['   ', '?'],
    ] as [$name, $initials]) {
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
        ->and($Main->leftNav)->toBeFalse()
        ->and($Main->adminNav)->toBeFalse()
        ->and($Main->settingsNav)->toBeFalse()
        ->and($Main->nav())->toBeFalse()
        ->and(Main::from([Main::classnames => 'bg-base-200'])->classnames)->toBe('bg-base-200')
        ->and(Main::from([Main::adminNav => true])->nav())->toBeTrue()
        ->and(Main::from([Main::settingsNav => true])->nav())->toBeTrue();

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
