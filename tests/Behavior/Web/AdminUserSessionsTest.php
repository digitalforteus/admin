<?php

use App\Models\Session;
use App\Models\User;
use App\Modules\Admin\Sessions\SessionDeleteController;
use App\Routes\Admin;
use App\Routes\Web;
use App\Sources\Db\App\Sessions;
use App\Sources\Db\App\Users;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;

function webSessionGuard(): SessionGuard
{
    $Guard = Auth::guard();

    if (! $Guard instanceof SessionGuard) {
        throw new RuntimeException('The web guard must use sessions.');
    }

    return $Guard;
}

test('the user page links to the users sessions and shows the last session time', function (): void {
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
});

test('the authenticated user and roles are not queried twice on their own page', function (): void {
    $Admin = adminUser();
    $queries = [];
    Session::resolveConnection()->listen(static function (QueryExecuted $QueryExecuted) use (&$queries): void {
        $queries[] = [$QueryExecuted->sql, $QueryExecuted->bindings];
    });

    $this->withSession([webSessionGuard()->getName() => $Admin->id])
        ->get(Admin::user->url([Admin::userParameter => $Admin->id]))
        ->assertOk();

    expect($queries)
        ->toHaveCount(5)
        ->toHaveSameSize(array_unique(array_map(serialize(...), $queries)));
});

test('an admin views one users sessions, the whole list, and searches them by email', function (): void {
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
});

test('guests and non admins cannot view or revoke sessions', function (): void {
    $User = User::factory()->createOne();
    $url = Admin::sessions->value.'?'.http_build_query([Admin::userParameter => $User->id]);
    $sessionUrl = Admin::session->url([Admin::sessionParameter => 'protected-session']);

    $this->get($url)->assertRedirect(Web::login->value);
    $this->delete($sessionUrl)->assertRedirect(Web::login->value);

    $this->actingAs($User)->get($url)->assertForbidden();
    $this->actingAs($User)->delete($sessionUrl)->assertForbidden();
    $this->actingAs($User)->delete(Admin::sessions->value)->assertForbidden();
});

test('an admin revokes one session, refuses a missing one, and clears every session for one user', function (): void {
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
});

test('revoking or clearing the current session signs the admin out', function (): void {
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
});
