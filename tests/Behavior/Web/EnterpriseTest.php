<?php

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Helpers\Slug;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Memberships\MembershipQuery;
use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Routes\ContextRoute;
use App\Routes\Web;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;

test('an enterprise is named once, owned by the account that named it, and holds organizations addressed inside it', function (): void {
    $index = ContextRoute::enterpriseIndex->url();
    $create = ContextRoute::enterpriseCreate->url();

    $this->get($index)->assertRedirect(Web::login->value);
    $this->post($index, [EnterpriseForm::name => 'Acme Holdings'])->assertRedirect(Web::login->value);

    $this->assertDatabaseCount(Enterprises::table(), 0);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get($index)
        ->assertOk()
        ->assertSee('data-enterprises-empty', false)
        ->assertSee('data-enterprise-add', false)
        ->assertSee($create);

    $this->actingAs($User)
        ->get($create)
        ->assertOk()
        ->assertSee('data-enterprise-create', false)
        ->assertSee('Enterprise Name');

    // Standing is held where it is granted, so an enterprise is reachable by the
    // account that made it without anything else having to exist inside it first.
    $this->actingAs($User)
        ->from($create)
        ->post($index, [EnterpriseForm::name => '  Acme   Holdings  '])
        ->assertSessionHas('status', 'Enterprise created.');

    $Enterprise = Enterprise::query()->sole();

    expect($Enterprise->name)->toBe('Acme Holdings')
        ->and($Enterprise->slug)->toBe('acme-holdings')
        ->and(Organization::query()->count())->toBe(0)
        ->and(MembershipQuery::held(Depth::enterprise, $Enterprise, $User))->toBe(MemberRole::owner);

    $parameters = atEnterprise($Enterprise);
    $enterprise = ContextRoute::enterprise->url($parameters);
    $settings = ContextRoute::enterpriseSettings->url($parameters);
    $organizations = ContextRoute::organizationIndex->url($parameters);
    $organizationCreate = ContextRoute::organizationCreate->url($parameters);

    $this->actingAs($User)
        ->get($enterprise)
        ->assertOk()
        ->assertSee('Acme Holdings')
        ->assertSee('data-organizations-empty', false)
        ->assertSee('data-organization-add', false)
        ->assertSee('data-enterprise-settings', false)
        ->assertSee($organizationCreate);

    // An organization is addressed inside its enterprise, so its segment is settled
    // against the names taken there and nowhere else.
    $this->actingAs($User)
        ->get($organizationCreate)
        ->assertOk()
        ->assertSee('data-organization-create', false)
        ->assertSee('Organization Name');

    foreach (['Acme Inc.', 'Acme Inc.', '???'] as $name) {
        $this->actingAs($User)
            ->from($organizationCreate)
            ->post($organizations, [OrganizationForm::name => $name])
            ->assertSessionHas('status', 'Organization created.');
    }

    expect(Organization::query()->pluck(Organizations::slug->value)->all())
        ->toEqualCanonicalizing(['acme-inc', 'acme-inc-2', Slug::fallback]);

    // A second enterprise may hold the same segment, because an organization is never
    // addressed without naming the enterprise around it.
    $this->actingAs($User)
        ->from($create)
        ->post($index, [EnterpriseForm::name => 'Globex Group'])
        ->assertSessionHas('status', 'Enterprise created.');

    $Second = Enterprise::query()->where(Enterprises::slug->value, 'globex-group')->sole();

    $this->actingAs($User)
        ->from(ContextRoute::organizationCreate->url(atEnterprise($Second)))
        ->post(ContextRoute::organizationIndex->url(atEnterprise($Second)), [
            OrganizationForm::name => 'Acme Inc.',
        ])
        ->assertSessionHas('status', 'Organization created.');

    expect(Organization::query()->where(Organizations::slug->value, 'acme-inc')->count())->toBe(2);

    $this->actingAs($User)
        ->from($organizationCreate)
        ->post($organizations, [OrganizationForm::name => ''])
        ->assertRedirect($organizationCreate)
        ->assertSessionHasErrors(OrganizationForm::name);

    // Every write answers to the rules the form declares, and a rejection comes back
    // to the form it was sent from.
    $this->actingAs($User)
        ->from($create)
        ->post($index, [EnterpriseForm::name => ''])
        ->assertRedirect($create)
        ->assertSessionHasErrors(EnterpriseForm::name);

    $this->actingAs($User)
        ->from($create)
        ->post($index, [EnterpriseForm::name => str_repeat('a', 256)])
        ->assertSessionHasErrors(EnterpriseForm::name)
        ->assertSessionHasInput(EnterpriseForm::name, str_repeat('a', 256));

    expect(Enterprise::query()->count())->toBe(2);

    // The name is changed by the standing held at this depth and by no other.
    $this->actingAs($User)
        ->get($settings)
        ->assertOk()
        ->assertSee('data-enterprise-form', false)
        ->assertSee('data-enterprise-organization', false)
        ->assertSee('Acme Holdings');

    $this->actingAs($User)
        ->from($settings)
        ->post($settings, [EnterpriseForm::name => 'Acme Group'])
        ->assertSessionHas('status', 'Enterprise updated.');

    expect($Enterprise->refresh()->name)->toBe('Acme Group');

    $this->actingAs($User)
        ->from($settings)
        ->post($settings, [EnterpriseForm::name => ''])
        ->assertSessionHasErrors(EnterpriseForm::name);

    // An account admitted below the top reads the enterprise and changes nothing.
    $Member = User::factory()->createOne();
    MembershipQuery::grant(Depth::enterprise, $Enterprise, $Member, MemberRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($enterprise)
        ->assertOk()
        ->assertDontSee('data-enterprise-settings', false)
        ->assertDontSee('data-organization-add', false);

    $this->actingAs($Member)->get($settings)->assertForbidden();
    $this->actingAs($Member)->post($settings, [EnterpriseForm::name => 'Theirs'])->assertForbidden();
    $this->actingAs($Member)->get($organizationCreate)->assertForbidden();
    $this->actingAs($Member)
        ->post($organizations, [OrganizationForm::name => 'Theirs'])
        ->assertForbidden();

    expect($Enterprise->refresh()->name)->toBe('Acme Group');

    // Existence is not public: an account holding nothing inside is told there is
    // nothing there rather than that it may not have it.
    $Stranger = User::factory()->createOne();

    $this->forgetCredentials()
        ->actingAs($Stranger)
        ->get($enterprise)
        ->assertNotFound();

    $this->actingAs($Stranger)->get($settings)->assertNotFound();
    $this->actingAs($Stranger)->get($organizationCreate)->assertNotFound();
    $this->actingAs($Stranger)
        ->post($organizations, [OrganizationForm::name => 'Sneaky'])
        ->assertNotFound();

    $this->actingAs($User)
        ->get(ContextRoute::enterprise->url([ContextRoute::enterpriseParameter => 'missing']))
        ->assertNotFound();

    $this->actingAs($Stranger)
        ->get($index)
        ->assertOk()
        ->assertSee('data-enterprises-empty', false)
        ->assertDontSee('Acme Group');

    $this->actingAs($User)
        ->get($index)
        ->assertOk()
        ->assertSee('data-enterprise-row', false)
        ->assertSee('Acme Group')
        ->assertSee('Globex Group');
});
