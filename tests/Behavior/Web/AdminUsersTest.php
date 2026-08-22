<?php

use App\Helpers\OauthProviderId;
use App\Helpers\SortDirection;
use App\Models\Session;
use App\Models\User;
use App\Modules\Admin\Users\UsersQuery;
use App\Modules\Admin\Users\UsersRequest;
use App\Routes\Admin;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Sessions;
use App\Sources\Db\App\Users;
use App\View\DataModels\UsersTable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** @param  array<string, string|int>  $query */
function usersUrl(array $query = []): string
{
    return $query === [] ? Admin::users->value : Admin::users->value.'?'.http_build_query($query);
}

test('guests and users without the admin role are refused', function (): void {
    $this->get(Admin::users->value)->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->createOne())
        ->get(Admin::users->value)
        ->assertForbidden();
});

test('a row carries the avatar, the initials fallback, the last session and the verification chip', function (): void {
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
        ->assertSee("classList.add('bg-neutral')", false)
        ->assertSee('<span class="hidden text-xs" title="AL">AL</span>', false)
        ->assertSee('data-last-session-column', false)
        ->assertSee($lastSessionAt->diffForHumans());

    $content = (string) $TestResponse->getContent();

    expect($content)
        ->toContain('<a href="'.Admin::user->url([Admin::userParameter => $Verified->id]).'" class="link" title="'.$Verified->email.'">'.$Verified->email.'</a>')
        ->toContain('<a href="'.Admin::user->url([Admin::userParameter => $Unverified->id]).'" class="link" title="'.$Unverified->email.'">'.$Unverified->email.'</a>')
        ->and(substr_count($content, '>Unverified</span>'))->toBe(1);
});

test('the page queries its user table once', function (): void {
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
});

test('every column gets a heading linking to its own ordering, and the current one flips direction', function (): void {
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
});

test('the search box filters by name and email, keeps the term, and says when nothing matches', function (): void {
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
});

test('an unrecognised ordering falls back, and the one asked for is the one the query runs', function (): void {
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
});

test('a second page is reachable and keeps the term', function (): void {
    User::factory()->count(UsersQuery::perPage + 1)->create([Users::name->value => 'Paginated Person']);

    $this->actingAs(adminUser())
        ->get(usersUrl([UsersRequest::search => 'Paginated Person', 'page' => 2]))
        ->assertOk()
        ->assertSee('Paginated Person');
});
