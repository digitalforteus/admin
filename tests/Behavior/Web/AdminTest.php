<?php

use App\Helpers\CacheKey;
use App\Helpers\Gravatar;
use App\Helpers\OauthProviderId;
use App\Helpers\Role;
use App\Helpers\SortDirection;
use App\Helpers\Theme;
use App\Models\OauthProvider;
use App\Models\Session;
use App\Models\User;
use App\Modules\Admin\Content\ContentUpdateRequest;
use App\Modules\Admin\Sessions\SessionDeleteController;
use App\Modules\Admin\Users\Delete\UserDeleteController;
use App\Modules\Admin\Users\Update\UsersUpdateRequest;
use App\Modules\Admin\Users\UsersQuery;
use App\Modules\Admin\Users\UsersRequest;
use App\Routes\Admin;
use App\Routes\AdminLink;
use App\Routes\ApiRoute;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Roles;
use App\Sources\Db\App\Sessions;
use App\Sources\Db\App\Users;
use App\View\DataModels\UsersTable;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function admin(): User
{
    $User = User::factory()->createOne();
    $User->assignRole(Role::admin->value);

    return $User;
}

/** @param  array<string, string|int>  $query */
function usersUrl(array $query = []): string
{
    return $query === [] ? Admin::users->value : Admin::users->value.'?'.http_build_query($query);
}

function editUrl(User $User): string
{
    return Admin::user->url([Admin::userParameter => $User->id]);
}

/**
 * @param  array<string, string|bool>  $overrides
 * @return array<string, string|bool>
 */
function payload(User $User, array $overrides = []): array
{
    return [
        UsersUpdateRequest::name => $User->name,
        UsersUpdateRequest::email => $User->email,
        UsersUpdateRequest::verified => $User->email_verified_at !== null,
        UsersUpdateRequest::admin => $User->hasRole(Role::admin->value),
        UsersUpdateRequest::theme => $User->theme->value,
        ...$overrides,
    ];
}

function webSessionGuard(): SessionGuard
{
    $Guard = Auth::guard();

    if (! $Guard instanceof SessionGuard) {
        throw new RuntimeException('The web guard must use sessions.');
    }

    return $Guard;
}

function userDeleteUrl(string $userId): string
{
    return Admin::user->url([Admin::userParameter => $userId]);
}

function providerDeleteUrl(User $User, string $providerId): string
{
    return Admin::userProvider->url([
        Admin::userParameter => $User->id,
        Admin::providerParameter => $providerId,
    ]);
}

function providerFor(User $User): OauthProvider
{
    return $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => 'provider-'.$User->id,
        OauthProviders::name->value => 'Google User',
        OauthProviders::given_name->value => 'Google',
        OauthProviders::family_name->value => 'User',
        OauthProviders::picture->value => 'https://example.com/avatar.jpg',
        OauthProviders::email->value => $User->email,
        OauthProviders::email_verified->value => true,
        OauthProviders::hd->value => 'example.com',
        OauthProviders::id->value => 'provider-'.$User->id,
        OauthProviders::verified_email->value => true,
        OauthProviders::link->value => 'https://example.com/profile',
    ]);
}

test('the admin pages belong to the one role there is, and list, edit, delete and revoke everything they manage', function (): void {
    $this->assertDatabaseHas(Roles::table(), [
        Roles::name->value => Role::admin->value,
        Roles::guard_name->value => config('auth.defaults.guard'),
    ]);

    expect(Role::cases())->toBe([Role::admin])
        ->and(User::factory()->createOne()->getRoleNames()->all())->toBeEmpty();

    $this->get(Admin::index->value)->assertRedirect(Web::login->value);
    $this->get(Admin::links->value)->assertRedirect(Web::login->value);

    $User = User::factory()->createOne();

    $this->actingAs($User)->get(Admin::index->value)->assertForbidden();
    $this->actingAs($User)->get(Admin::links->value)->assertForbidden();

    $this->actingAs(admin())
        ->get(Admin::index->value)
        ->assertOk()
        ->assertSee('data-admin-dashboard', false)
        ->assertSee('data-registered-users', false)
        ->assertSee('aria-label="Admin"', false)
        ->assertDontSee('aria-label="Primary"', false)
        ->assertSee('Links')
        ->assertSee(Admin::links->value);

    // Some of the links leave the application, where nothing else would notice one that
    // stopped resolving.
    $this->actingAs(admin());

    $TestResponse = $this->get(Admin::links->value)
        ->assertOk()
        ->assertSee('Links');

    // A link is broken when it resolves to nothing, which a redirect to another page
    // this application serves is not.
    foreach (AdminLink::routes() as $link) {
        $TestResponse->assertSee($link[AdminLink::url]);

        expect($this->get($link[AdminLink::url])->getStatusCode())->toBeLessThan(400);
    }

    $this->actingAs(User::factory()->createOne())
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('aria-label="Admin"', false);

    foreach (CacheKey::cases() as $key) {
        Cache::forget($key->value);
    }

    $this->forgetCredentials();

    $this->get(Admin::content->value)->assertRedirect(Web::login->value);

    $this->actingAs(adminUser())
        ->get(Admin::content->value)
        ->assertOk()
        ->assertSee(CacheKey::robots->value)
        ->assertSee(CacheKey::llms->value)
        ->assertSee(CacheKey::api_readme->value);

    $this->get(Web::robots->value)->assertSee((string) file_get_contents(resource_path(CacheKey::robots->value)), false);
    $this->get(Web::llms->value)->assertSee((string) file_get_contents(resource_path(CacheKey::llms->value)), false);
    $this->getJson(ApiRoute::readme->value)
        ->assertJsonPath('data.content', (string) file_get_contents(resource_path(CacheKey::api_readme->value)));

    $this->actingAs(adminUser())
        ->post(Admin::content->value)
        ->assertSessionHasErrors([
            ContentUpdateRequest::robots,
            ContentUpdateRequest::llms,
            ContentUpdateRequest::api_readme,
        ]);

    $content = [
        ContentUpdateRequest::robots => 'User-agent: *\nDisallow: /private',
        ContentUpdateRequest::llms => '# Custom agent guide',
        ContentUpdateRequest::api_readme => '# Custom API guide',
    ];

    $this->actingAs(adminUser())
        ->post(Admin::content->value, $content)
        ->assertRedirect()
        ->assertSessionHas('status', 'Site content updated.');

    expect(Cache::get(CacheKey::robots->value))->toBe($content[ContentUpdateRequest::robots])
        ->and(Cache::get(CacheKey::llms->value))->toBe($content[ContentUpdateRequest::llms])
        ->and(Cache::get(CacheKey::api_readme->value))->toBe($content[ContentUpdateRequest::api_readme]);

    $this->get(Web::robots->value)->assertSee($content[ContentUpdateRequest::robots], false);
    $this->get(Web::llms->value)->assertSee($content[ContentUpdateRequest::llms], false);
    $this->getJson(ApiRoute::readme->value)->assertJsonPath('data.content', $content[ContentUpdateRequest::api_readme]);

    foreach (CacheKey::cases() as $key) {
        Cache::forget($key->value);
    }

    $this->forgetCredentials();

    $this->get(Admin::users->value)->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->createOne())
        ->get(Admin::users->value)
        ->assertForbidden();

    $Admin = adminUser();
    $Admin->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => '123456789',
        OauthProviders::name->value => $Admin->name,
        OauthProviders::given_name->value => 'Admin',
        OauthProviders::family_name->value => 'User',
        OauthProviders::picture->value => 'https://example.com/avatar.jpg',
        OauthProviders::email->value => $Admin->email,
        OauthProviders::email_verified->value => true,
        OauthProviders::hd->value => null,
        OauthProviders::id->value => '123456789',
        OauthProviders::verified_email->value => true,
    ]);

    $Verified = User::factory()->createOne([
        Users::name->value => 'Ada Lovelace',
        Users::email_verified_at->value => now(),
    ]);
    $Unverified = User::factory()->createOne([Users::email_verified_at->value => null]);

    $lastSessionAt = now()->subHour()->startOfSecond();
    Session::query()->create([
        Sessions::id->value => 'listed-user-session',
        Sessions::user_id->value => $Verified->id,
        Sessions::payload->value => 'payload',
        Sessions::last_activity->value => $lastSessionAt->timestamp,
    ]);

    $TestResponse = $this->actingAs($Admin)
        ->get(Admin::users->value)
        ->assertOk()
        ->assertSee('data-admin-users', false)
        ->assertSee($Admin->name)
        ->assertSee($Admin->email)
        ->assertDontSee('Email Verified At')
        ->assertSee('https://example.com/avatar.jpg')
        ->assertSee('alt="'.e($Admin->name).'"', false)
        ->assertSee('avatar-placeholder', false)
        ->assertSee("classList.add('bg-neutral', 'text-neutral-content')", false)
        ->assertSee('title="'.e($Admin->name).'"', false)
        ->assertSee('data-last-session-column', false)
        ->assertSee($lastSessionAt->diffForHumans());

    $content = (string) $TestResponse->getContent();

    expect($content)
        ->toContain('<a href="'.Admin::user->url([Admin::userParameter => $Verified->id]).'" class="link" title="'.$Verified->email.'">'.$Verified->email.'</a>')
        ->toContain('<a href="'.Admin::user->url([Admin::userParameter => $Unverified->id]).'" class="link" title="'.$Unverified->email.'">'.$Unverified->email.'</a>')
        ->and(substr_count($content, '>Unverified</span>'))->toBe(1);

    Auth::forgetGuards();
    $this->flushSession();

    $queries = [];
    User::resolveConnection()->listen(static function (QueryExecuted $QueryExecuted) use (&$queries): void {
        $queries[] = $QueryExecuted->sql;
    });

    $this->actingAs(adminUser())
        ->get(Admin::users->value)
        ->assertOk();

    expect(collect($queries)->filter(
        static fn (string $query): bool => str_contains($query, 'count(*) as `aggregate` from `users`'),
    ))->toHaveCount(1)
        ->and(collect($queries)->filter(
            static fn (string $query): bool => str_contains($query, 'from `users` order by'),
        ))->toHaveCount(1)
        ->and(collect($queries)->filter(
            static fn (string $query): bool => str_contains($query, 'from `oauth_providers`'),
        ))->toHaveCount(2);

    $TestResponse = $this->actingAs(adminUser())
        ->get(Admin::users->value)
        ->assertOk();

    foreach (UsersTable::columns() as $Column) {
        $TestResponse->assertSee(Str::headline($Column->name))
            ->assertSee(UsersRequest::sort.'='.$Column->value, false);
    }

    $this->actingAs(adminUser())
        ->get(usersUrl([
            UsersRequest::sort => Users::email->value,
            UsersRequest::direction => SortDirection::asc->value,
        ]))
        ->assertOk()
        ->assertSee(UsersRequest::direction.'='.SortDirection::desc->value, false);

    $Match = User::factory()->createOne([
        Users::name->value => 'Ada Lovelace',
        Users::email->value => 'ada@example.com',
    ]);
    $Other = User::factory()->createOne([
        Users::name->value => 'Grace Hopper',
        Users::email->value => 'grace@example.com',
    ]);

    $this->actingAs(adminUser())
        ->get(usersUrl([UsersRequest::search => 'Ada Lovelace']))
        ->assertOk()
        ->assertSee($Match->name)
        ->assertDontSee($Other->name);

    $this->actingAs(adminUser())
        ->get(usersUrl([UsersRequest::search => 'ada@example.com']))
        ->assertOk()
        ->assertSee($Match->email)
        ->assertDontSee($Other->email)
        ->assertSee('value="ada@example.com"', false);

    $this->actingAs(adminUser())
        ->get(usersUrl([UsersRequest::search => 'nobody-by-that-name']))
        ->assertOk()
        ->assertSee('data-users-empty', false);

    User::factory()->count(3)->create();

    $this->actingAs(adminUser())
        ->get(usersUrl([
            UsersRequest::sort => Users::password->value,
            UsersRequest::direction => 'sideways',
        ]))
        ->assertOk();

    $Fallback = UsersRequest::of(Request::create(usersUrl([
        UsersRequest::sort => Users::password->value,
        UsersRequest::direction => 'sideways',
    ])));

    expect($Fallback->sort)->toBe(UsersTable::columns()[0])
        ->and($Fallback->direction)->toBe(SortDirection::asc);

    $UsersRequest = UsersRequest::of(Request::create(usersUrl([
        UsersRequest::sort => Users::email->value,
        UsersRequest::direction => SortDirection::desc->value,
    ])));

    $emails = UsersQuery::get($UsersRequest)->getCollection()->pluck(Users::email->value)->all();

    expect($emails)->toBe(collect($emails)->sortDesc()->values()->all());

    User::factory()->count(UsersQuery::perPage + 1)->create([Users::name->value => 'Paginated Person']);

    $this->actingAs(adminUser())
        ->get(usersUrl([UsersRequest::search => 'Paginated Person', 'page' => 2]))
        ->assertOk()
        ->assertSee('Paginated Person');

    $this->forgetCredentials();

    $User = User::factory()->createOne();

    $this->get(editUrl($User))->assertRedirect(Web::login->value);
    $this->post(editUrl($User), payload($User))->assertRedirect(Web::login->value);

    $this->actingAs($User)->get(editUrl($User))->assertForbidden();
    $this->actingAs($User)->post(editUrl($User), payload($User))->assertForbidden();

    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->get(editUrl($User))
        ->assertOk()
        ->assertSee($User->name)
        ->assertSee('src="'.e(Gravatar::url($User->email)).'"', false)
        ->assertSee('alt="'.e($User->name).'"', false)
        ->assertSee('value="'.$User->email.'"', false)
        ->assertSee('data-user-status', false)
        ->assertSee($User->id)
        ->assertSee('data-record-details', false)
        ->assertSee('data-authentication-providers', false)
        ->assertSee('data-delete-user', false);

    // The index is paginated, so the account is searched for rather than assumed
    // to be on the page every other segment has been adding accounts to.
    $this->actingAs(adminUser())
        ->get(usersUrl([UsersRequest::search => $User->email]))
        ->assertOk()
        ->assertSee('<a href="'.editUrl($User).'" class="link" title="'.$User->email.'">'.$User->email.'</a>', false)
        ->assertDontSee('class="btn btn-ghost btn-xs">Edit</a>', false);

    $this->actingAs(adminUser())
        ->get(Admin::user->url([Admin::userParameter => 'nobody']))
        ->assertNotFound();

    $this->actingAs(adminUser())
        ->post(Admin::user->url([Admin::userParameter => 'nobody']), [
            UsersUpdateRequest::name => 'Ada Lovelace',
            UsersUpdateRequest::email => 'ada.lovelace@example.com',
        ])
        ->assertNotFound();

    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [
            UsersUpdateRequest::name => 'Ada Lovelace',
            UsersUpdateRequest::email => 'ada.lovelace@example.com',
        ]))
        ->assertRedirect(editUrl($User))
        ->assertSessionHas('status');

    $this->assertDatabaseHas(Users::table(), [
        Users::id->value => $User->getKey(),
        Users::name->value => 'Ada Lovelace',
        Users::email->value => 'ada.lovelace@example.com',
    ]);

    $this->actingAs(adminUser())
        ->post(editUrl($User), payload($User->refresh(), [
            UsersUpdateRequest::theme => Theme::dark->value,
            UsersUpdateRequest::password => 'new-password-1234',
            UsersUpdateRequest::password_confirmation => 'new-password-1234',
        ]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->theme)->toBe(Theme::dark)
        ->and(Hash::check('new-password-1234', $User->password))->toBeTrue();

    // The address the account already holds is its own, so uniqueness lets it through.
    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::name => 'Grace Hopper']))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->name)->toBe('Grace Hopper');

    $User = User::factory()->createOne();
    $Other = User::factory()->createOne();

    $this->actingAs(adminUser());

    $this->from(editUrl($User))
        ->post(editUrl($User), payload($User, [
            UsersUpdateRequest::theme => 'sepia',
            UsersUpdateRequest::password => 'new-password-1234',
            UsersUpdateRequest::password_confirmation => 'mismatch',
        ]))
        ->assertSessionHasErrors([UsersUpdateRequest::theme, UsersUpdateRequest::password]);

    $this->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::email => $Other->email]))
        ->assertRedirect(editUrl($User))
        ->assertSessionHasErrors(UsersUpdateRequest::email);

    expect($User->refresh()->email)->not->toBe($Other->email);

    $this->from(editUrl($User))
        ->post(editUrl($User), payload($User, [
            UsersUpdateRequest::name => '',
            UsersUpdateRequest::email => 'ada.lovelace@example.com',
        ]))
        ->assertSessionHasErrors(UsersUpdateRequest::name);

    $this->get(editUrl($User))
        ->assertOk()
        ->assertSee('value="ada.lovelace@example.com"', false);

    $User = User::factory()->createOne();

    expect($User->email_verified_at)->not->toBeNull();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::verified => false]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->email_verified_at)->toBeNull();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::verified => true]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->email_verified_at)->not->toBeNull();

    $verified = now()->subMonth();
    $Held = User::factory()->createOne([Users::email_verified_at->value => $verified]);

    $this->actingAs(adminUser())
        ->from(editUrl($Held))
        ->post(editUrl($Held), payload($Held, [UsersUpdateRequest::verified => true]))
        ->assertSessionHasNoErrors();

    expect($Held->refresh()->email_verified_at?->toDateTimeString())->toBe($verified->toDateTimeString());

    // Revoking it from the account making the request is the one change that cannot be
    // undone from these pages, because the page that undoes it is behind the role.
    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::admin => true]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->hasRole(Role::admin->value))->toBeTrue();

    $this->actingAs(adminUser())
        ->from(editUrl($User))
        ->post(editUrl($User), payload($User, [UsersUpdateRequest::admin => false]))
        ->assertSessionHasNoErrors();

    expect($User->refresh()->hasRole(Role::admin->value))->toBeFalse();

    $Admin = adminUser();

    $this->actingAs($Admin)
        ->from(editUrl($Admin))
        ->post(editUrl($Admin), payload($Admin, [UsersUpdateRequest::admin => false]))
        ->assertRedirect(editUrl($Admin))
        ->assertSessionHasErrors(UsersUpdateRequest::admin);

    expect($Admin->refresh()->hasRole(Role::admin->value))->toBeTrue();

    $this->forgetCredentials();

    $User = User::factory()->createOne();
    $lastSessionAt = now()->subHour()->startOfSecond();
    Session::query()->create([
        Sessions::id->value => 'user-detail-session',
        Sessions::user_id->value => $User->id,
        Sessions::payload->value => 'payload',
        Sessions::last_activity->value => $lastSessionAt->timestamp,
    ]);

    $this->actingAs(adminUser())
        ->get(Admin::user->url([Admin::userParameter => $User->id]))
        ->assertOk()
        ->assertSee(Admin::sessions->value.'?'.http_build_query([Admin::userParameter => $User->id]), false)
        ->assertSee('data-record-details', false)
        ->assertSee($lastSessionAt->toDayDateTimeString());

    Auth::forgetGuards();
    $this->flushSession();

    $Admin = adminUser();
    $detailQueries = [];
    Session::resolveConnection()->listen(static function (QueryExecuted $QueryExecuted) use (&$detailQueries): void {
        $detailQueries[] = [$QueryExecuted->sql, $QueryExecuted->bindings];
    });

    $this->withSession([webSessionGuard()->getName() => $Admin->id])
        ->get(Admin::user->url([Admin::userParameter => $Admin->id]))
        ->assertOk();

    expect($detailQueries)
        ->toHaveCount(6)
        ->toHaveSameSize(array_unique(array_map(serialize(...), $detailQueries)));

    $User = User::factory()->createOne([Users::email->value => 'matching@example.com']);
    $Other = User::factory()->createOne([Users::email->value => 'other@example.com']);

    Session::query()->create([
        Sessions::id->value => 'managed-web-session',
        Sessions::user_id->value => $User->id,
        Sessions::ip_address->value => '127.0.0.1',
        Sessions::user_agent->value => 'Example Browser',
        Sessions::payload->value => 'private payload',
        Sessions::last_activity->value => now()->timestamp,
    ]);
    Session::query()->create([
        Sessions::id->value => 'other-session',
        Sessions::user_id->value => $Other->id,
        Sessions::payload->value => 'private payload',
        Sessions::last_activity->value => now()->timestamp,
    ]);

    $this->actingAs(adminUser())
        ->get(Admin::sessions->value.'?'.http_build_query([Admin::userParameter => $User->id]))
        ->assertOk()
        ->assertSee('managed-web-session')
        ->assertSee('127.0.0.1')
        ->assertSee('Example Browser')
        ->assertDontSee('private payload')
        ->assertDontSee('other-session');

    $this->actingAs(adminUser())
        ->get(Admin::sessions->value)
        ->assertOk()
        ->assertSee('managed-web-session')
        ->assertSee('other-session')
        ->assertSee($User->id);

    $this->actingAs(adminUser())
        ->get(Admin::sessions->value.'?'.http_build_query(['email' => 'matching@']))
        ->assertOk()
        ->assertSee('managed-web-session')
        ->assertSee($User->email)
        ->assertDontSee('other-session');

    Auth::forgetGuards();
    $this->flushSession();

    $User = User::factory()->createOne();
    $url = Admin::sessions->value.'?'.http_build_query([Admin::userParameter => $User->id]);
    $sessionUrl = Admin::session->url([Admin::sessionParameter => 'protected-session']);

    $this->get($url)->assertRedirect(Web::login->value);
    $this->delete($sessionUrl)->assertRedirect(Web::login->value);

    $this->actingAs($User)->get($url)->assertForbidden();
    $this->actingAs($User)->delete($sessionUrl)->assertForbidden();
    $this->actingAs($User)->delete(Admin::sessions->value)->assertForbidden();

    $User = User::factory()->createOne([Users::remember_token->value => 'remembered']);
    $OtherUser = User::factory()->createOne([Users::remember_token->value => 'still-remembered']);
    foreach ([[$User, 'revoked-session'], [$User, 'cleared-session'], [$OtherUser, 'retained-session']] as [$SessionUser, $sessionId]) {
        Session::query()->create([
            Sessions::id->value => $sessionId,
            Sessions::user_id->value => $SessionUser->id,
            Sessions::payload->value => 'payload',
            Sessions::last_activity->value => now()->timestamp,
        ]);
    }

    $this->actingAs(adminUser())
        ->delete(Admin::session->url([Admin::sessionParameter => 'revoked-session']))
        ->assertRedirect(Admin::sessions->value)
        ->assertSessionHas('status', 'Session revoked.');

    $this->assertDatabaseMissing(Sessions::table(), [Sessions::id->value => 'revoked-session']);
    expect($User->refresh()->remember_token)->toBeNull();

    $this->actingAs(adminUser())
        ->delete(Admin::session->url([Admin::sessionParameter => 'missing-session']))
        ->assertNotFound();

    // Clearing is scoped to one account, so the unscoped page offers no control and
    // the unscoped request is not an operation at all.
    $Admin = adminUser();

    $this->actingAs($Admin)
        ->get(Admin::sessions->value)
        ->assertOk()
        ->assertDontSee('data-clear-user-sessions', false);
    $this->actingAs($Admin)
        ->get(Admin::sessions->value.'?'.http_build_query([Admin::userParameter => $Admin->id]))
        ->assertOk()
        ->assertSee('data-clear-user-sessions', false);
    $this->actingAs($Admin)->delete(Admin::sessions->value)->assertNotFound();

    $User->forceFill([Users::remember_token->value => 'remembered'])->save();

    $this->actingAs($Admin)
        ->delete(Admin::sessions->value, [Admin::userParameter => $User->id])
        ->assertRedirect(Admin::sessions->value.'?'.http_build_query([Admin::userParameter => $User->id]))
        ->assertSessionHas('status', 'All user sessions cleared.');

    $this->assertAuthenticated();
    $this->assertDatabaseMissing(Sessions::table(), [Sessions::id->value => 'cleared-session']);
    $this->assertDatabaseHas(Sessions::table(), [Sessions::id->value => 'retained-session']);
    expect($User->refresh()->remember_token)->toBeNull()
        ->and($OtherUser->refresh()->remember_token)->toBe('still-remembered');

    $Admin = adminUser();

    $this->actingAs($Admin)
        ->delete(Admin::sessions->value, [Admin::userParameter => $Admin->id])
        ->assertRedirect(Web::login->value)
        ->assertSessionHas('status', 'All user sessions cleared.');

    $this->assertGuest();

    Auth::login($Admin);
    $sessionId = str_repeat('a', 40);
    $Session = new Store('test', new ArraySessionHandler(120));
    $Session->setId($sessionId);
    $Request = Request::create(Admin::sessions->value, 'DELETE');
    $Request->setLaravelSession($Session);
    Session::query()->create([
        Sessions::id->value => $sessionId,
        Sessions::user_id->value => $Admin->id,
        Sessions::payload->value => 'payload',
        Sessions::last_activity->value => now()->timestamp,
    ]);

    $Response = app(SessionDeleteController::class)($Request, $sessionId);

    expect($Response->getTargetUrl())->toBe(url(Web::login->value));
    $this->assertGuest();

    $this->forgetCredentials();

    $User = User::factory()->createOne();
    $OauthProvider = providerFor($User);

    $this->actingAs(adminUser())
        ->get(userDeleteUrl($User->id))
        ->assertOk()
        ->assertSee('Google User')
        ->assertSee('example.com')
        ->assertSee('provider-'.$User->id)
        ->assertSee('https://example.com/avatar.jpg')
        ->assertSee('https://example.com/profile')
        ->assertSee(providerDeleteUrl($User, $OauthProvider->sub));

    $this->actingAs(adminUser())
        ->from(userDeleteUrl($User->id))
        ->delete(providerDeleteUrl($User, $OauthProvider->sub))
        ->assertRedirect(userDeleteUrl($User->id))
        ->assertSessionHas('status', 'Sign-in provider removed.');

    expect($OauthProvider->fresh())->toBeNull()
        ->and($User->fresh())->not->toBeNull();

    Auth::forgetGuards();
    $User = User::factory()->createOne();
    $OauthProvider = providerFor($User);

    $this->delete(userDeleteUrl($User->id))->assertRedirect(Web::login->value);
    $this->delete(providerDeleteUrl($User, $OauthProvider->sub))->assertRedirect(Web::login->value);

    $RegularUser = User::factory()->createOne();
    $this->actingAs($RegularUser)->delete(userDeleteUrl($User->id))->assertForbidden();
    $this->actingAs($RegularUser)->delete(providerDeleteUrl($User, $OauthProvider->sub))->assertForbidden();

    $User = User::factory()->createOne();
    $Other = User::factory()->createOne();
    $OauthProvider = providerFor($User);
    $Admin = adminUser();

    $this->actingAs($Admin)
        ->delete(providerDeleteUrl($Other, $OauthProvider->sub))
        ->assertNotFound();

    expect($OauthProvider->fresh())->not->toBeNull();

    $this->actingAs($Admin)->delete(userDeleteUrl('nobody'))->assertNotFound();
    $this->actingAs($Admin)->delete(providerDeleteUrl($User, 'nobody'))->assertNotFound();
    $this->actingAs($Admin)->delete(Admin::userProvider->url([
        Admin::userParameter => 'nobody',
        Admin::providerParameter => 'nobody',
    ]))->assertNotFound();

    $User = User::factory()->createOne();

    $this->actingAs(adminUser())
        ->from(userDeleteUrl($User->id))
        ->delete(userDeleteUrl($User->id), [UserDeleteController::confirmation => 'DELETE'])
        ->assertRedirect(userDeleteUrl($User->id))
        ->assertSessionHasErrors('delete');

    expect($User->fresh())->not->toBeNull();

    $Admin = adminUser();

    $this->actingAs($Admin)
        ->from(userDeleteUrl($Admin->id))
        ->delete(userDeleteUrl($Admin->id), [UserDeleteController::confirmation => 'delete'])
        ->assertRedirect(userDeleteUrl($Admin->id))
        ->assertSessionHasErrors('delete');

    expect($Admin->fresh())->not->toBeNull();

    $User = User::factory()->createOne();
    providerFor($User);
    $User->assignRole(Role::admin->value);
    $Token = $User->createToken('Delete me');

    User::query()->getConnection()->table('password_reset_tokens')->insert([
        Users::email->value => $User->email,
        'token' => 'hashed-token',
    ]);
    Session::query()->create([
        'id' => 'user-session',
        'user_id' => $User->id,
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs(adminUser())
        ->delete(userDeleteUrl($User->id), [UserDeleteController::confirmation => 'delete'])
        ->assertRedirect(Admin::users->value)
        ->assertSessionHas('status', 'User deleted.');

    expect($User->fresh())->toBeNull();
    $this->assertDatabaseMissing(OauthProviders::table(), [OauthProviders::user_id->value => $User->id]);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $User->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $Token->accessToken->getKey()]);
    $this->assertDatabaseMissing('password_reset_tokens', [Users::email->value => $User->email]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $User->id]);
});
