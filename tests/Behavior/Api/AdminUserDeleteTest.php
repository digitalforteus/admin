<?php

use App\Models\User;
use App\Modules\Api\Admin\User\Delete\AdminUserDeleteResponse;
use App\Modules\Api\Admin\User\UserParameter;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;

test('a user is deleted, and only by an administrator who names one that exists', function (): void {
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
