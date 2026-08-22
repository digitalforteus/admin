<?php

use App\Helpers\Role;
use App\Models\User;
use App\Routes\Admin;
use App\Routes\AdminLink;
use App\Routes\Web;
use App\Sources\Db\App\Roles;

function admin(): User
{
    $User = User::factory()->createOne();
    $User->assignRole(Role::admin->value);

    return $User;
}

test('the migration creates the only role there is, and a registered account holds none of it', function (): void {
    $this->assertDatabaseHas(Roles::table(), [
        Roles::name->value => Role::admin->value,
        Roles::guard_name->value => config('auth.defaults.guard'),
    ]);

    expect(Role::cases())->toBe([Role::admin])
        ->and(User::factory()->createOne()->getRoleNames()->all())->toBeEmpty();
});

test('guests and users without the role are refused the dashboard and the links page', function (): void {
    $this->get(Admin::index->value)->assertRedirect(Web::login->value);
    $this->get(Admin::links->value)->assertRedirect(Web::login->value);

    $User = User::factory()->createOne();

    $this->actingAs($User)->get(Admin::index->value)->assertForbidden();
    $this->actingAs($User)->get(Admin::links->value)->assertForbidden();
});

test('the dashboard renders with the admin rail, which leads with the links page', function (): void {
    $this->actingAs(admin())
        ->get(Admin::index->value)
        ->assertOk()
        ->assertSee('data-admin-dashboard', false)
        ->assertSee('data-registered-users', false)
        ->assertSee('aria-label="Admin"', false)
        ->assertDontSee('aria-label="Primary"', false)
        ->assertSee('Links')
        ->assertSee(Admin::links->value);
});

// Some of the links leave the application, where nothing else would notice one that
// stopped resolving.
test('the links page lists every marked route, and every one of them is reachable', function (): void {
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
});

test('the default rail is left alone off the admin pages', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('aria-label="Admin"', false);
});
