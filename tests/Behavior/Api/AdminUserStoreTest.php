<?php

use App\Modules\Api\Admin\User\Store\AdminUserStoreRequest;
use App\Modules\Api\Admin\User\Store\AdminUserStoreResponse;
use App\Modules\Api\Support\ApiResponse;
use App\Routes\Admin;

test('a user is created by an administrator, and never without a name', function (): void {
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
});
