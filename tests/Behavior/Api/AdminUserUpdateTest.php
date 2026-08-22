<?php

use App\Models\User;
use App\Modules\Api\Admin\User\Update\AdminUserUpdateRequest;
use App\Modules\Api\Admin\User\Update\AdminUserUpdateResponse;
use App\Modules\Api\Admin\User\UserParameter;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;

test('a user is updated by an administrator who names one that exists and gives it a name', function (): void {
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
});
