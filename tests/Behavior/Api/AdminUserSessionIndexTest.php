<?php

use App\Models\Session;
use App\Models\User;
use App\Modules\Api\Admin\User\Session\Index\AdminUserSessionIndexResponse;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;
use App\Sources\Db\App\Sessions;
use Illuminate\Support\Facades\Auth;

test('a users sessions are listed for an administrator, and for nobody and nothing else', function (): void {
    $missing = Admin::api_user_sessions->url([Admin::userParameter => 'missing']);

    $this->assertMatchesSchema($this->getJson($missing))->assertUnauthorized();

    Auth::forgetGuards();
    $Admin = adminUser();
    $this->actingAs($Admin)->getJson($missing)->assertNotFound();

    $User = User::factory()->createOne();
    Session::query()->create([
        Sessions::id->value => 'managed-session',
        Sessions::user_id->value => $User->id,
        Sessions::ip_address->value => '127.0.0.1',
        Sessions::user_agent->value => 'Example Browser',
        Sessions::payload->value => 'private payload',
        Sessions::last_activity->value => now()->timestamp,
    ]);

    $this->assertMatchesSchema($this->actingAs($Admin)->getJson(Admin::api_user_sessions->url([
        Admin::userParameter => $User->id,
    ])))
        ->assertOk()
        ->assertJsonPath(ApiResponse::type, class_basename(AdminUserSessionIndexResponse::class))
        ->assertJsonPath('data.sessions.0.id', 'managed-session')
        ->assertJsonMissing(['payload' => 'private payload']);
});
