<?php

use App\AppConfig;
use App\Helpers\BrandLink;
use App\Helpers\Role;
use App\Helpers\SessionKey;
use App\Helpers\SvgName;
use App\Helpers\Theme;
use App\Models\User;
use App\Plugins\AdminLink\AdminLink;
use App\Plugins\AdminLink\AdminLinkPlugin;
use App\Plugins\DescribesPlugin;
use App\Plugins\PluginIndex;
use App\Plugins\RouteTags;
use App\Plugins\TaggedRoute;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\MiddlewareTag;
use App\Routes\RouteIndex;
use App\Routes\Web;
use App\Sources\Db\App\Users;
use App\View\DataModels\AdminNav;
use App\View\DataModels\Avatar;
use App\View\DataModels\DescribesNav;
use App\View\DataModels\DocsNav;
use App\View\DataModels\LeftNav;
use App\View\DataModels\Main;
use App\View\DataModels\Nav;
use App\View\DataModels\NavItem;
use App\View\DataModels\NavLink;
use App\View\DataModels\NavRail;
use App\View\DataModels\SettingsNav;
use App\View\DataModels\Svg;
use App\View\DataModels\Topnav;
use App\View\DataModels\UserMenu;
use App\View\ViewDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
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
        foreach (RouteTags::in($enum, AdminLink::class) as $TaggedRoute) {
            $orders[$TaggedRoute->url] = AdminLinkPlugin::order($TaggedRoute);
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

    expect($Nested->active())->toBeFalse();

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('lg:pl-56')
        ->assertDontSee('Open navigation');

    $User = User::factory()->createOne();

    foreach ([Web::home, Web::contact, Web::privacyPolicy, Web::termsOfService] as $Web) {
        $this->actingAs($User)
            ->get($Web->value)
            ->assertOk()
            ->assertDontSee('aria-label="Primary"', false)
            ->assertDontSee('lg:pl-56')
            ->assertSee('Open navigation')
            ->assertSee('href="'.Web::contact->value.'"', false);
    }

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

    $attributes = new ReflectionClass(AdminLink::class)->getAttributes(Attribute::class);

    expect($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS_CONSTANT)
        ->and(array_column(PluginIndex::adminLink->routes(), DescribesPlugin::url))->toContain(
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
    $listed = array_column(PluginIndex::adminLink->routes(), DescribesPlugin::url);

    $sequence = array_map(static fn (string $url): int => $orders[$url], $listed);
    $ascending = $sequence;
    sort($ascending);

    $Bare = RouteTags::in(RouteIndexStub::class, AdminLink::class);

    expect($listed)->toEqualCanonicalizing(array_keys($orders))
        ->and($listed)->toHaveSameSize($orders)
        ->and($sequence)->toBe($ascending)
        ->and(new AdminLink()->order)->toBeNull()
        ->and($Bare)->toHaveCount(1)
        ->and($Bare[0]->name)->toBe(RouteIndexStub::bare->name)
        ->and($Bare[0]->url)->toBe(RouteIndexStub::bare->value)
        ->and(AdminLinkPlugin::order($Bare[0]))->toBe(PHP_INT_MAX);

    // An enum reports what it holds, in the order it declares it. Sorting is the job of the
    // query where every index's links meet. A case tagged in an enum the registry does not
    // name is not the application's routing, so the page does not display it.
    $tagged = array_map(
        static fn (TaggedRoute $TaggedRoute): string => $TaggedRoute->name,
        RouteTags::in(Web::class, AdminLink::class),
    );

    $declared = array_values(array_filter(
        array_map(static fn (Web $Case): string => $Case->name, Web::cases()),
        static fn (string $name): bool => in_array($name, $tagged, true),
    ));

    expect($tagged)->not->toBeEmpty()
        ->and($tagged)->toBe($declared)
        ->and(RouteTags::in(Auth::class, AdminLink::class))->toBeEmpty()
        ->and($Bare)->not->toBeEmpty()
        ->and(array_column(PluginIndex::adminLink->routes(), DescribesPlugin::url))
        ->not->toContain(RouteIndexStub::bare->value);

    // A plugin is installed by naming it on the registry, and a page asks the registry
    // rather than the class: every case it holds answers with a plugin declaring the
    // attribute its tagged routes carry.
    foreach (PluginIndex::cases() as $Plugin) {
        expect(is_subclass_of($Plugin->plugin(), DescribesPlugin::class))->toBeTrue()
            ->and(class_exists($Plugin->plugin()::attribute()))->toBeTrue();
    }

    expect(PluginIndex::adminLink->plugin())->toBe(AdminLinkPlugin::class)
        ->and(AdminLinkPlugin::attribute())->toBe(AdminLink::class);

    $this->actingAs(User::factory()->createOne());
    app()->instance('request', Request::create(Web::home->value));

    $None = Topnav::from([]);

    expect($None->nav)->toBeNull()
        ->and($None->items())->toEqual(LeftNav::items())
        ->and($None->dropdown())->toBeTrue()
        ->and(Topnav::from([Topnav::nav => Nav::settings])->dropdown())->toBeTrue();

    $this->forgetCredentials();
    app()->instance('request', Request::create(Web::home->value));

    expect(Topnav::from([])->dropdown())->toBeFalse();

    foreach ([
        [Nav::left, LeftNav::items()],
        [Nav::admin, AdminNav::items()],
        [Nav::settings, SettingsNav::items()],
    ] as [$Nav, $items]) {
        expect(Topnav::from([Topnav::nav => $Nav])->nav)->toBe($Nav)
            ->and(Topnav::from([Topnav::nav => $Nav])->items())->toEqual($items);
    }

    expect(Nav::admin->enum())->toBe(AdminNav::class)
        ->and(array_column(Nav::cases(), 'value'))
        ->toBe([AdminNav::class, SettingsNav::class, DocsNav::class, LeftNav::class])
        ->and(View::exists('components.nav-rail'))->toBeTrue()
        ->and(View::exists('components.nav-link'))->toBeTrue();

    // The rail a case renders is the items its enum declares, in the order it declares
    // them, so the only thing a new navigation owes this test is the order it claims.
    $declared = [
        Nav::admin->name => [Admin::index, Admin::users, Admin::sessions, Admin::content, Admin::links],
        Nav::settings->name => [
            Auth::settingsProfile,
            Auth::settingsAppearance,
            Auth::settingsSecurity,
            Auth::settingsCredentials,
            Auth::settingsSessions,
        ],
        Nav::docs->name => [Web::docsApi, Web::docsMcp],
        Nav::left->name => [Web::home, Web::contact],
    ];

    foreach (Nav::cases() as $Nav) {
        $NavRail = NavRail::from($Nav->navRail());

        expect(enum_exists($Nav->enum()))->toBeTrue()
            ->and(is_subclass_of($Nav->enum(), DescribesNav::class))->toBeTrue()
            ->and($Nav->items())->toEqual($Nav->enum()::items())
            ->and($NavRail->label)->not->toBeEmpty()
            ->and($NavRail->items)->toEqual($Nav->items())
            ->and($NavRail->items)->not->toBeEmpty()
            ->and(collect($NavRail->items)->pluck('route')->all())->toBe($declared[$Nav->name]);

        foreach ($NavRail->items as $NavItem) {
            $NavLink = NavLink::from($NavItem->navLink());

            expect($NavItem)->toBeInstanceOf(NavItem::class)
                ->and(ViewDirectory::svg->has($NavItem->icon))->toBeTrue()
                ->and($NavLink->url)->toBe($NavItem->url())
                ->and($NavLink->label)->toBe($NavItem->label)
                ->and(Svg::from($NavLink->svg)->name)->toBe($NavItem->icon)
                ->and($NavLink->classnames)->toBeEmpty()
                ->and($NavLink->classes())->toBe(['' => false, 'menu-active' => $NavItem->active()]);
        }
    }

    // The dropdown renders the same link the rail does, styled for where it stands.
    $Styled = NavLink::from([
        ...LeftNav::home->item()->navLink(),
        NavLink::classnames => 'items-center gap-3 my-1 font-medium',
    ]);

    expect($Styled->classes())->toBe(['items-center gap-3 my-1 font-medium' => true, 'menu-active' => $Styled->active])
        ->and(static fn () => NavLink::from([]))->toThrow(PropertyRequiredException::class)
        ->and(static fn () => NavRail::from([]))->toThrow(PropertyRequiredException::class);

    $this->actingAs(User::factory()->createOne());

    foreach ([
        [Auth::settingsProfile->value, Nav::settings],
        [Web::docsApi->value, Nav::docs],
        [Auth::dashboard->value, Nav::left],
    ] as [$path, $Nav]) {
        app()->instance('request', Request::create($path));

        expect(Nav::active())->toBe($Nav)
            ->and($Nav->visible())->toBeTrue();
    }

    app()->instance('request', Request::create(Web::home->value));

    expect(Nav::active())->toBeNull();

    $this->forgetCredentials();

    foreach ([
        ['John Doe', 'JD'],
        ['John Quincy Doe', 'JD'],
        ['Prince', 'P'],
        ['  john   doe  ', 'JD'],
        ['', '?'],
        ['   ', '?'],
    ] as [$name, $initials]) {
        expect(Avatar::from(UserMenu::from([UserMenu::name => $name])->avatar())->initials())->toBe($initials);
    }

    expect(UserMenu::from([UserMenu::name => 'John Doe', UserMenu::picture => 'https://example.com/avatar.jpg'])->avatar())
        ->toBe([Avatar::name => 'John Doe', Avatar::picture => 'https://example.com/avatar.jpg'])
        ->and(UserMenu::from([UserMenu::name => 'John Doe'])->avatar()[Avatar::picture])->toBeNull();

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
        ->assertSee('<span class="hidden text-sm" title="JD">JD</span>', false);

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
        ->assertSee('<meta name="description" content="Sign in to your '.$name.' client account.">', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    $this->get(Web::register->value)
        ->assertOk()
        ->assertSee('<meta name="description" content="Create your account.">', false)
        ->assertSee("<meta property=\"og:title\" content=\"Register - $name\">", false)
        ->assertSee('<meta property="og:description" content="Create your account.">', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    Head::flush();

    $this->get(Web::contact->value)
        ->assertOk()
        ->assertSee('<meta name="robots" content="all">', false);

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

    app()->instance('request', Request::create(Config::string('app.url')));

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
        ->assertSee('digitalforte_referral_click')
        ->assertSee('href="'.e(BrandLink::header_lockup->url()).'"', false)
        ->assertSee('href="'.e(BrandLink::footer_attribution->url()).'"', false);

    // An outward link is published with the path it is visited at, the root included: a query
    // hung straight off a bare address is rewritten by whatever follows the link, so what is
    // advertised and what is fetched differ by a separator, and the crawler that follows it
    // records the difference as a hop rather than as the destination. The configured address is
    // an environment value and may or may not end in a separator, so a destination that
    // concatenates one has to tolerate both spellings or it doubles it.
    expect(BrandLink::header_lockup->url())->toContain('/?utm_source=')
        ->and(BrandLink::footer_attribution->url())->toContain('/?utm_source=')
        ->and(BrandLink::showcase->url())->toEndWith('/showcase');

    $configured = Config::string('brand.digitalforte_url');

    config()->set('brand.digitalforte_url', 'https://example.test/');

    expect(BrandLink::showcase->url())->toBe('https://example.test/showcase')
        ->and(BrandLink::header_lockup->url())->toStartWith('https://example.test/?utm_source=');

    config()->set('brand.digitalforte_url', $configured);

    config()->set('brand.attribution', false);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-digitalforte-link', false)
        ->assertDontSee('digitalforte_referral_click')
        ->assertSee(Config::string('app.name'));
});
