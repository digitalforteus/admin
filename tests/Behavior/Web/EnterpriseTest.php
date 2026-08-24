<?php

use App\Helpers\OrganizationRole;
use App\Helpers\Slug;
use App\Http\Middleware\ResolveEnterprise;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Enterprises\EnterpriseContext;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Enterprises\EnterpriseQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\EnterpriseRoute;
use App\Routes\OrganizationRoute;
use App\Routes\Web;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('an enterprise is created with its first organization, addressed by slug, and reachable only through a membership inside it', function (): void {
    $create = EnterpriseRoute::create->url();

    $this->get($create)->assertRedirect(Web::login->value);
    $this->post($create, [
        EnterpriseForm::name => 'Acme Holdings',
        EnterpriseForm::organization => 'Acme Inc.',
    ])->assertRedirect(Web::login->value);

    $this->assertDatabaseCount(Enterprises::table(), 0);

    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get($create)
        ->assertOk()
        ->assertSee('data-enterprise-create', false)
        ->assertSee('Enterprise Name')
        ->assertSee('First Organization Name');

    // An enterprise is never committed empty: standing at this depth is read from the
    // organizations inside, so one holding none is unreachable by the account that
    // asked for it — which is why naming the first organization is part of asking.
    $this->actingAs($User)
        ->from($create)
        ->post($create, [
            EnterpriseForm::name => 'Acme Holdings',
            EnterpriseForm::organization => 'Acme Inc.',
        ])
        ->assertSessionHas('status', 'Enterprise created.');

    $Enterprise = Enterprise::query()->sole();
    $Organization = Organization::query()->sole();

    expect($Enterprise->name)->toBe('Acme Holdings')
        ->and($Enterprise->slug)->toBe('acme-holdings')
        ->and($Organization->name)->toBe('Acme Inc.')
        ->and($Organization->enterprise_id)->toBe($Enterprise->id)
        ->and($Organization->created_by)->toBe($User->id)
        ->and($Organization->creator?->id)->toBe($User->id)
        ->and(MembershipQuery::role($Organization, $User))->toBe(OrganizationRole::owner)
        ->and(EnterpriseQuery::manages($Enterprise, $User))->toBeTrue();

    $parameters = [EnterpriseRoute::enterpriseParameter => $Enterprise->slug];
    $index = EnterpriseRoute::index->url($parameters);

    $this->actingAs($User)
        ->get($index)
        ->assertOk()
        ->assertSee('Acme Holdings')
        ->assertSee('data-enterprise-organization', false)
        ->assertSee('data-enterprise-organization-add', false)
        ->assertSee('data-enterprise-settings', false)
        ->assertSee(OrganizationRoute::index->url([
            OrganizationRoute::organizationParameter => $Organization->slug,
        ]));

    // A second organization is added inside the enterprise the address names, and the
    // segment it is addressed by is settled against every name already taken.
    $organizationCreate = EnterpriseRoute::organizationCreate->url($parameters);

    $this->actingAs($User)
        ->get($organizationCreate)
        ->assertOk()
        ->assertSee('data-organization-create', false)
        ->assertSee('Organization Name');

    $this->actingAs($User)
        ->from($organizationCreate)
        ->post($organizationCreate, [OrganizationForm::name => '  Globex   Corp.  '])
        ->assertSessionHas('status', 'Organization created.');

    $this->actingAs($User)
        ->from($organizationCreate)
        ->post($organizationCreate, [OrganizationForm::name => 'Globex Corp.'])
        ->assertSessionHas('status', 'Organization created.');

    $this->actingAs($User)
        ->from($organizationCreate)
        ->post($organizationCreate, [OrganizationForm::name => '???'])
        ->assertSessionHas('status', 'Organization created.');

    expect(Organization::query()->pluck(Organizations::slug->value)->all())
        ->toEqualCanonicalizing(['acme-inc', 'globex-corp', 'globex-corp-2', Slug::fallback])
        ->and(Organization::query()->where(Organizations::slug->value, 'globex-corp')->sole()->name)
        ->toBe('Globex Corp.')
        ->and(EnterpriseQuery::organizations($Enterprise, $User))->toHaveCount(4);

    // Every write answers to the same rules the form declares, and a rejected one
    // comes back to the form it was sent from with what was typed.
    $this->actingAs($User)
        ->from($create)
        ->post($create, [EnterpriseForm::name => '', EnterpriseForm::organization => ''])
        ->assertRedirect($create)
        ->assertSessionHasErrors([EnterpriseForm::name, EnterpriseForm::organization]);

    $this->actingAs($User)
        ->from($create)
        ->followingRedirects()
        ->post($create, [EnterpriseForm::name => str_repeat('a', 256), EnterpriseForm::organization => 'Ok'])
        ->assertOk()
        ->assertSee('The name field must not be greater than 255 characters.');

    $this->actingAs($User)
        ->from($organizationCreate)
        ->post($organizationCreate, [OrganizationForm::name => str_repeat('a', 256)])
        ->assertSessionHasErrors(OrganizationForm::name)
        ->assertSessionHasInput(OrganizationForm::name, str_repeat('a', 256));

    expect(Enterprise::query()->count())->toBe(1);

    // The name is changed by an account holding the top standing inside, and by no
    // other: standing is never held at this depth, only read from below it.
    $settings = EnterpriseRoute::settings->url($parameters);

    $this->actingAs($User)
        ->get($settings)
        ->assertOk()
        ->assertSee('data-enterprise-form', false)
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

    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($index)
        ->assertOk()
        ->assertDontSee('data-enterprise-settings', false);

    $this->actingAs($Member)->get($settings)->assertForbidden();
    $this->actingAs($Member)
        ->post($settings, [EnterpriseForm::name => 'Renamed'])
        ->assertForbidden();

    expect(EnterpriseQuery::manages($Enterprise, $Member))->toBeFalse()
        ->and($Enterprise->refresh()->name)->toBe('Acme Group');

    // Existence is not public either: an account with no membership inside is told
    // there is nothing there rather than that it may not have it.
    $Stranger = User::factory()->createOne();

    $this->forgetCredentials()
        ->actingAs($Stranger)
        ->get($index)
        ->assertNotFound();

    $this->actingAs($Stranger)->get($settings)->assertNotFound();
    $this->actingAs($Stranger)->get($organizationCreate)->assertNotFound();
    $this->actingAs($Stranger)
        ->post($organizationCreate, [OrganizationForm::name => 'Sneaky'])
        ->assertNotFound();

    $this->actingAs($User)
        ->get(EnterpriseRoute::index->url([EnterpriseRoute::enterpriseParameter => 'missing']))
        ->assertNotFound();

    expect(EnterpriseQuery::forUser($Stranger))->toBeEmpty()
        ->and(collect(EnterpriseQuery::forUser($User))->pluck(Enterprises::slug->value)->all())
        ->toBe(['acme-holdings']);

    // A path naming no enterprise resolves none, and every reader of the context is
    // required to cope with that rather than assume one is always present.
    $this->forgetCredentials();

    $Request = Request::create('/nowhere');
    app()->instance('request', $Request);

    $Passed = new ResolveEnterprise()->handle($Request, static fn (): Response => new Response('passed'));

    expect($Passed->getContent())->toBe('passed')
        ->and(EnterpriseContext::enterprise())->toBeNull()
        ->and(EnterpriseContext::active())->toBeFalse();

    // An account holding a membership reads the enterprise its organization sits in
    // without the address ever naming it.
    $this->actingAs($User)
        ->get(OrganizationRoute::index->url([
            OrganizationRoute::organizationParameter => $Organization->slug,
        ]))
        ->assertOk();

    expect(EnterpriseContext::enterprise()?->id)->toBe($Enterprise->id)
        ->and(EnterpriseContext::active())->toBeTrue();
});
