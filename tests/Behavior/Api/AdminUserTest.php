<?php

use App\Helpers\HttpVerb;
use App\Models\Session;
use App\Models\User;
use App\Modules\Api\Admin\User\Delete\AdminUserDeleteResponse;
use App\Modules\Api\Admin\User\Index\AdminUserIndexResponse;
use App\Modules\Api\Admin\User\Session\Index\AdminUserSessionIndexResponse;
use App\Modules\Api\Admin\User\Show\AdminUserShowResponse;
use App\Modules\Api\Admin\User\Store\AdminUserStoreRequest;
use App\Modules\Api\Admin\User\Store\AdminUserStoreResponse;
use App\Modules\Api\Admin\User\Update\AdminUserUpdateRequest;
use App\Modules\Api\Admin\User\Update\AdminUserUpdateResponse;
use App\Modules\Api\Admin\User\UserParameter;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;
use App\Sources\Db\App\Sessions;

test('the admin api lists, shows, creates, updates and deletes a user, and lists their sessions, for an administrator alone', function (): void {
    $this->assertMatchesSchema($this->getJson(Admin::api_users->value))->assertStatus(401);

    $User = adminUser();
    $ManagedUser = User::factory()->createOne(['name' => 'Managed User']);

    $this->assertMatchesSchema($this->actingAs($User)->getJson(Admin::api_users->value))
        ->assertStatus(200)
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::type => class_basename(AdminUserIndexResponse::class),
        ])
        ->assertJsonFragment(['id' => $ManagedUser->id, 'name' => 'Managed User']);

    $this->forgetCredentials();
    $token = $User->createToken('admin-api', [HttpVerb::get->ability(Admin::api_users->value)])->plainTextToken;

    $this->assertMatchesSchema($this->withToken($token)->getJson(Admin::api_users->value))->assertOk();

    $this->forgetCredentials();

    $this->assertMatchesSchema(
        $this->getJson(Admin::api_user->url([UserParameter::name => 'example']))
    )->assertStatus(401);

    $User = adminUser();

    $this->assertMatchesSchema(
        $this->actingAs($User)->getJson(Admin::api_user->url([UserParameter::name => 'missing']))
    )->assertStatus(404);

    $ManagedUser = User::factory()->createOne(['name' => 'Managed User']);

    $this->assertMatchesSchema(
        $this->actingAs($User)->getJson(Admin::api_user->url([UserParameter::name => $ManagedUser->id]))
    )
        ->assertStatus(200)
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::type => class_basename(AdminUserShowResponse::class),
        ])
        ->assertJsonPath('data.id', $ManagedUser->id)
        ->assertJsonPath('data.name', 'Managed User');

    $this->forgetCredentials();

    $this->assertMatchesSchema($this->postJson(Admin::api_users->value, [
        AdminUserStoreRequest::name => 'example',
        AdminUserStoreRequest::email => 'managed@example.com',
        AdminUserStoreRequest::password => 'example',
    ]))->assertStatus(401);

    $User = adminUser();

    // Blank is a server policy, not a published constraint: the document admits
    // the empty string, so the request still conforms and the 422 is reachable.
    $this->assertMatchesSchema($this->actingAs($User)->postJson(Admin::api_users->value, [
        AdminUserStoreRequest::name => '',
        AdminUserStoreRequest::email => 'managed@example.com',
        AdminUserStoreRequest::password => 'example',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(AdminUserStoreRequest::name);

    $this->assertMatchesSchema($this->actingAs($User)->postJson(Admin::api_users->value, [
        AdminUserStoreRequest::name => 'Managed User',
        AdminUserStoreRequest::email => 'managed@example.com',
        AdminUserStoreRequest::password => 'secret-password',
    ]))
        ->assertStatus(201)
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::type => class_basename(AdminUserStoreResponse::class),
        ])
        ->assertJsonPath('data.name', 'Managed User')
        ->assertJsonPath('data.email', 'managed@example.com');

    $this->assertDatabaseHas('users', ['email' => 'managed@example.com']);

    $this->forgetCredentials();

    $this->assertMatchesSchema(
        $this->patchJson(Admin::api_user->url([UserParameter::name => 'example']))
    )->assertStatus(401);

    $User = adminUser();

    $this->assertMatchesSchema($this->actingAs($User)->patchJson(
        Admin::api_user->url([UserParameter::name => 'missing']),
        [AdminUserUpdateRequest::name => 'Missing User'],
    ))->assertStatus(404);

    $ManagedUser = User::factory()->createOne();
    $url = Admin::api_user->url([UserParameter::name => $ManagedUser->id]);

    $this->assertMatchesSchema($this->actingAs($User)->patchJson($url, [
        AdminUserUpdateRequest::name => '',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(AdminUserUpdateRequest::name);

    $this->assertMatchesSchema($this->actingAs($User)->patchJson($url, [
        AdminUserUpdateRequest::name => 'Updated User',
    ]))
        ->assertStatus(200)
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::type => class_basename(AdminUserUpdateResponse::class),
        ])
        ->assertJsonPath('data.name', 'Updated User');

    expect($ManagedUser->refresh()->name)->toBe('Updated User');

    $this->forgetCredentials();

    $missing = Admin::api_user_sessions->url([Admin::userParameter => 'missing']);

    $this->forgetCredentials();

    $this->assertMatchesSchema($this->getJson($missing))->assertUnauthorized();

    $this->forgetCredentials();
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

    $this->forgetCredentials();

    $this->assertMatchesSchema(
        $this->deleteJson(Admin::api_user->url([UserParameter::name => 'example']))
    )->assertStatus(401);

    $User = adminUser();

    $this->assertMatchesSchema(
        $this->actingAs($User)->deleteJson(Admin::api_user->url([UserParameter::name => 'missing']))
    )->assertStatus(404);

    $ManagedUser = User::factory()->createOne();

    $this->assertMatchesSchema(
        $this->actingAs($User)->deleteJson(Admin::api_user->url([UserParameter::name => $ManagedUser->id]))
    )
        ->assertStatus(200)
        ->assertJson([
            ApiResponse::success => true,
            ApiResponse::type => class_basename(AdminUserDeleteResponse::class),
        ])
        ->assertJsonPath('data.id', $ManagedUser->id);

    $this->assertDatabaseMissing('users', ['id' => $ManagedUser->id]);
});
