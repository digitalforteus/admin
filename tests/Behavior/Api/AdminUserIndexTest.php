<?php

use App\Helpers\HttpVerb;
use App\Models\User;
use App\Modules\Api\Admin\User\Index\AdminUserIndexResponse;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;
use Illuminate\Support\Facades\Auth;

test('users are listed for an administrator, by session or by a token holding the ability', function (): void {
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

    Auth::forgetGuards();
    $token = $User->createToken('admin-api', [HttpVerb::get->ability(Admin::api_users->value)])->plainTextToken;

    $this->assertMatchesSchema($this->withToken($token)->getJson(Admin::api_users->value))->assertOk();
});
