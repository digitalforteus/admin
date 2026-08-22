<?php

use App\Models\User;
use App\Modules\Api\Admin\User\Show\AdminUserShowResponse;
use App\Modules\Api\Admin\User\UserParameter;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;

test('a user is shown to an administrator who names one that exists', function (): void {
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
});
