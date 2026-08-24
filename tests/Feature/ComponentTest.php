<?php

use App\Helpers\SortDirection;
use App\Helpers\SvgName;
use App\Models\User;
use App\Modules\Admin\Users\UsersQuery;
use App\Modules\Admin\Users\UsersRequest;
use App\Routes\Admin;
use App\Sources\Db\App\Users;
use App\View\DataModels\AuthCard;
use App\View\DataModels\Avatar;
use App\View\DataModels\CopyLink;
use App\View\DataModels\Fieldset;
use App\View\DataModels\Main;
use App\View\DataModels\PageHeader;
use App\View\DataModels\SortableHeader;
use App\View\DataModels\StatusToast;
use App\View\DataModels\Svg;
use App\View\DataModels\TextInput;
use App\View\DataModels\UserRow;
use App\View\DataModels\UsersTable;
use App\View\ViewDirectory;
use App\View\ViewName;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @param  array<string, mixed>  $overrides */
function usersTable(array $overrides = []): UsersTable
{
    return UsersTable::from([
        UsersTable::search => '',
        UsersTable::sort => Users::name,
        UsersTable::direction => SortDirection::asc,
        UsersTable::paginator => new LengthAwarePaginator([], 0, UsersQuery::perPage),
        ...$overrides,
    ]);
}

test('every component names a view that exists and reads one props array into typed properties', function (): void {
    foreach (ViewName::cases() as $case) {
        expect($case->exists())->toBeTrue();
    }

    $view = ViewName::main->render([Main::main => []]);

    expect($view->name())->toBe(ViewName::main->value)
        ->and($view->getData()[Main::main])->toBe([])
        ->and(ViewDirectory::svg->qualify(SvgName::logo))->toBe('svg.logo')
        ->and(ViewDirectory::svg->has(SvgName::logo))->toBeTrue();

    foreach (SvgName::cases() as $SvgName) {
        expect(ViewDirectory::svg->has($SvgName))->toBeTrue();
    }

    $paths = glob(resource_path('views/svg/*.blade.php')) ?: [];

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect(SvgName::from(basename($path, '.blade.php')))->toBeInstanceOf(SvgName::class);
    }

    $Svg = Svg::from([Svg::name => SvgName::email]);

    expect($Svg->name)->toBe(SvgName::email)
        ->and($Svg->classname)->toBeEmpty()
        ->and(static fn () => Svg::from([]))->toThrow(PropertyRequiredException::class);

    $Projected = Svg::from(TextInput::from([TextInput::name => 'email', TextInput::icon => SvgName::email])->svg());

    expect($Projected->name)->toBe(SvgName::email)
        ->and($Projected->classname)->toBe('h-4 w-4 opacity-70');

    $TextInput = TextInput::from([TextInput::name => 'email']);

    expect($TextInput->name)->toBe('email')
        ->and($TextInput->error)->toBe('email')
        ->and($TextInput->type)->toBe('text')
        ->and($TextInput->bag)->toBe('default')
        ->and($TextInput->configuredLabel)->toBe('value')
        ->and($TextInput->required)->toBeFalse()
        ->and($TextInput->configured)->toBeFalse()
        ->and($TextInput->legend)->toBeNull()
        ->and($TextInput->icon)->toBeNull()
        ->and($TextInput->title)->toBeNull()
        ->and($TextInput->placeholder)->toBeNull()
        ->and($TextInput->autocomplete)->toBeNull()
        ->and(static fn () => TextInput::from([]))->toThrow(PropertyRequiredException::class);

    $Overridden = TextInput::from([
        TextInput::name => 'email',
        TextInput::error => 'custom',
        TextInput::type => 'email',
        TextInput::bag => 'register_form',
        TextInput::value => 'explicit',
        TextInput::required => true,
    ]);

    expect($Overridden->error)->toBe('custom')
        ->and($Overridden->type)->toBe('email')
        ->and($Overridden->bag)->toBe('register_form')
        ->and($Overridden->value)->toBe('explicit')
        ->and($Overridden->required)->toBeTrue();

    $Store = new Store('test', new ArraySessionHandler(1));
    $Store->put('_old_input', ['email' => 'old@example.com', 'password' => 'secret']);
    request()->setLaravelSession($Store);

    expect(TextInput::from([TextInput::name => 'email'])->value)->toBe('old@example.com')
        ->and(TextInput::from([TextInput::name => 'password', TextInput::type => 'password'])->value)->toBeNull();

    $Fieldset = Fieldset::from([]);

    expect($Fieldset->bag)->toBe('default')
        ->and($Fieldset->required)->toBeFalse()
        ->and($Fieldset->legend)->toBeNull()
        ->and($Fieldset->name)->toBeNull()
        ->and($Fieldset->title)->toBeNull();

    $Overridden = Fieldset::from([
        Fieldset::legend => 'Email',
        Fieldset::name => 'email',
        Fieldset::bag => 'register_form',
        Fieldset::required => true,
        Fieldset::title => 'User email address',
    ]);

    expect($Overridden->legend)->toBe('Email')
        ->and($Overridden->name)->toBe('email')
        ->and($Overridden->bag)->toBe('register_form')
        ->and($Overridden->required)->toBeTrue()
        ->and($Overridden->title)->toBe('User email address');

    $Projected = Fieldset::from(
        TextInput::from([
            TextInput::name => 'email',
            TextInput::error => 'custom',
            TextInput::legend => 'Email',
            TextInput::required => true,
        ])->fieldset()
    );

    expect($Projected->name)->toBe('custom')
        ->and($Projected->legend)->toBe('Email')
        ->and($Projected->required)->toBeTrue()
        ->and($Projected->bag)->toBe('default');

    $PageHeader = PageHeader::from([]);

    expect($PageHeader->title)->toBeNull()
        ->and($PageHeader->classname)->toBe('card-title');

    $Overridden = PageHeader::from([
        PageHeader::title => 'Register',
        PageHeader::classname => 'text-lg',
    ]);

    expect($Overridden->title)->toBe('Register')
        ->and($Overridden->classname)->toBe('text-lg')
        ->and(PageHeader::from(AuthCard::from([AuthCard::title => 'Register'])->pageHeader())->title)->toBe('Register')
        ->and(PageHeader::from(AuthCard::from([])->pageHeader())->title)->toBeNull();

    $CopyLink = CopyLink::from([CopyLink::value => 'https://example.com/openapi.json']);

    expect($CopyLink->value)->toBe('https://example.com/openapi.json')
        ->and($CopyLink->label)->toBe('Copy link')
        ->and(static fn () => CopyLink::from([]))->toThrow(PropertyRequiredException::class)
        ->and(Svg::from($CopyLink->icon())->name)->toBe(SvgName::link)
        ->and(Svg::from($CopyLink->successIcon())->name)->toBe(SvgName::check_circle);

    $StatusToast = StatusToast::from();

    expect($StatusToast->sessionKey)->toBe('status')
        ->and($StatusToast->alert)->toBe('alert-success')
        ->and($StatusToast->message)->toBeNull();

    session()->put('status', 'Verification link sent!');
    session()->put('warning', 'Careful.');

    expect(StatusToast::from([])->message)->toBe('Verification link sent!')
        ->and(StatusToast::from([StatusToast::sessionKey => 'warning'])->message)->toBe('Careful.')
        ->and(StatusToast::from([StatusToast::sessionKey => 'missing'])->message)->toBeNull();

    $Passed = StatusToast::from([
        StatusToast::message => 'Passed.',
        StatusToast::alert => 'alert-error',
    ]);

    expect($Passed->message)->toBe('Passed.')
        ->and($Passed->alert)->toBe('alert-error');

    foreach (SortDirection::cases() as $SortDirection) {
        expect($SortDirection->opposite()->opposite())->toBe($SortDirection)
            ->and($SortDirection->opposite())->not->toBe($SortDirection)
            ->and(ViewDirectory::svg->has($SortDirection->icon()))->toBeTrue()
            ->and($SortDirection->aria())->toBeIn(['ascending', 'descending']);
    }

    $User = User::factory()->createOne();
    $UserRow = UserRow::from($User->toArray());

    expect($UserRow->name)->toBe($User->name)
        ->and($UserRow->email)->toBe($User->email)
        ->and(static fn () => UserRow::from([UserRow::name => 'Ada Lovelace']))
        ->toThrow(PropertyRequiredException::class);

    $Unverified = UserRow::from(User::factory()->unverified()->createOne()->toArray());

    expect($Unverified->cell(Users::email_verified_at))->toBe('—')
        ->and($Unverified->cell(Users::created_at))->toBe(now()->toFormattedDateString());

    $User = User::factory()->createOne();
    $cells = UserRow::from($User->toArray())->cells();
    $lastSessionAt = now()->subHour();

    expect($cells)->toHaveCount(count(UsersTable::columns()) + 1)
        ->and($cells[0])->toBe($User->name)
        ->and($cells[1])->toBe($User->email)
        ->and(UserRow::from($User->toArray())->lastSession())->toBe('—')
        ->and(UserRow::from([
            ...$User->toArray(),
            UserRow::last_session_at => $lastSessionAt->timestamp,
        ])->lastSession())->toBe($lastSessionAt->diffForHumans());

    $User = User::factory()->createOne([
        Users::name->value => 'Ada Byron Lovelace',
        Users::email->value => 'MyEmailAddress@example.com',
    ]);
    $attributes = $User->toArray();

    expect(Avatar::from(UserRow::from($attributes)->avatar())->initials())->toBe('AL')
        ->and(Avatar::from(UserRow::from([...$attributes, Users::name->value => ''])->avatar())->initials())->toBe('?')
        ->and(UserRow::from($attributes)->picture())
        ->toBe('https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=80&d=404&r=g')
        ->and(static fn () => UsersTable::from([UsersTable::search => '', UsersTable::sort => Users::name]))->toThrow(PropertyRequiredException::class);

    $Avatar = Avatar::from([]);

    expect($Avatar->name)->toBeEmpty()
        ->and($Avatar->picture)->toBeNull()
        ->and($Avatar->size)->toBe('w-9')
        ->and($Avatar->text)->toBe('text-sm')
        ->and($Avatar->initials())->toBe('?')
        ->and($Avatar->fallback)->toBeNull()
        ->and(Avatar::from(UserRow::from($attributes)->avatar()))
        ->toHaveProperties([
            Avatar::picture => UserRow::from($attributes)->picture(),
            Avatar::size => 'w-8',
            Avatar::fallback => SvgName::user,
        ]);

    // An avatar naming a fallback shows the thing's kind where a picture is missing,
    // and the initials are what a caller naming none still falls back to.
    $Fallback = Avatar::from([Avatar::name => 'Ada Lovelace', Avatar::fallback => SvgName::user]);

    expect(Svg::from($Fallback->svg())->name)->toBe(SvgName::user)
        ->and(Svg::from($Fallback->svg())->classname)->toBe('h-1/2 w-1/2')
        ->and($Fallback->initials())->toBe('AL');

    $properties = array_keys(get_class_vars(UserRow::class));

    foreach (UsersTable::columns() as $Column) {
        expect(Users::tryFrom($Column->value))->toBe($Column)
            ->and($properties)->toContain($Column->value);
    }

    // The span covers every column and the last session.
    expect(usersTable()->span())->toBe(count(UsersTable::columns()) + 1);

    $SortableHeader = usersTable()->headers()[0];

    expect($SortableHeader->label)->toBe('Name')
        ->and($SortableHeader->title)->toBe(Users::name->comment());

    $ordered = usersTable([UsersTable::sort => Users::email, UsersTable::direction => SortDirection::desc])->headers();

    $email = collect($ordered)->firstOrFail(
        static fn (SortableHeader $SortableHeader): bool => $SortableHeader->label === 'Email'
    );

    expect($email->sorted)->toBeTrue()
        ->and($email->ariaSort())->toBe(SortDirection::desc->aria())
        ->and($email->url)->toContain(UsersRequest::direction.'='.SortDirection::asc->value);

    $name = collect(usersTable([UsersTable::sort => Users::email])->headers())->firstOrFail(
        static fn (SortableHeader $SortableHeader): bool => $SortableHeader->label === 'Name'
    );

    expect($name->sorted)->toBeFalse()
        ->and($name->ariaSort())->toBe('none')
        ->and($name->url)->toContain(UsersRequest::direction.'='.SortDirection::asc->value)
        ->and(usersTable([UsersTable::search => 'ada'])->headers()[0]->url)
        ->toContain(UsersRequest::search.'=ada')
        ->and(usersTable()->headers()[0]->url)
        ->not->toContain(UsersRequest::search.'=');

    foreach (usersTable()->headers() as $SortableHeader) {
        expect(ViewDirectory::svg->has($SortableHeader->direction->icon()))->toBeTrue();
    }

    $UsersTable = usersTable([UsersTable::search => 'ada']);
    $TextInput = TextInput::from($UsersTable->searchInput());

    expect($TextInput->name)->toBe(UsersRequest::search)
        ->and($TextInput->value)->toBe('ada')
        ->and($UsersTable->action())->toBe(Admin::users->url())
        ->and($UsersTable->searching())->toBeTrue()
        // The ordering is carried forward so a search does not reset it.
        ->and(usersTable([UsersTable::sort => Users::email, UsersTable::direction => SortDirection::desc])->hidden())
        ->toBe([
            UsersRequest::sort => Users::email->value,
            UsersRequest::direction => SortDirection::desc->value,
        ])
        ->and(usersTable()->summary())->toBe('No users')
        ->and(usersTable()->rows())->toBeEmpty()
        ->and(usersTable()->previousUrl())->toBeNull()
        ->and(usersTable()->nextUrl())->toBeNull();

    $User = User::factory()->createOne();

    $Paginated = usersTable([
        UsersTable::paginator => new LengthAwarePaginator([$User], 1, UsersQuery::perPage),
    ]);

    expect($Paginated->rows()[0]->name)->toBe($User->name)
        ->and($Paginated->summary())->toBe('Showing 1–1 of 1');
});
