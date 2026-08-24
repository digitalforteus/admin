<?php

use App\Helpers\OrganizationRole;
use App\Helpers\SessionKey;
use App\Helpers\SvgName;
use App\Http\Middleware\ResolveOrganization;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\Authorize;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Routes\EnterpriseRoute;
use App\Routes\OrganizationRoute;
use App\Routes\Web;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use App\View\DataModels\Breadcrumb;
use App\View\DataModels\OrganizationNav;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('an organization page is addressed by slug, scoped to membership, and carries the chrome for the context it names', function (): void {
    $User = User::factory()->createOne();
    $Enterprise = Enterprise::factory()->createOne([Enterprises::name->value => 'Acme Holdings']);
    $Organization = memberOrganization($User, attributes: [
        Organizations::enterprise_id->value => $Enterprise->id,
        Organizations::name->value => 'Acme Inc.',
        Organizations::slug->value => 'acme',
    ]);
    $Other = memberOrganization($User, attributes: [
        Organizations::enterprise_id->value => $Enterprise->id,
        Organizations::name->value => 'Globex Corp.',
        Organizations::slug->value => 'globex',
    ]);
    // A second enterprise the same account belongs to: the organization dropdown is
    // scoped to the enterprise the address names, the enterprise dropdown is not.
    $Elsewhere = Enterprise::factory()->createOne([Enterprises::name->value => 'Umbrella Group']);
    memberOrganization($User, attributes: [
        Organizations::enterprise_id->value => $Elsewhere->id,
        Organizations::name->value => 'Umbrella Ltd.',
        Organizations::slug->value => 'umbrella',
    ]);
    $Stranger = Organization::factory()->createOne([
        Organizations::name->value => 'Initech LLC',
        Organizations::slug->value => 'initech',
    ]);

    $url = OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'acme']);

    $this->get($url)->assertRedirect(Web::login->value);

    $this->forgetCredentials()
        ->actingAs($User)
        ->get($url)
        ->assertOk()
        ->assertSee('Acme Inc.')
        ->assertSee('Globex Corp.')
        ->assertDontSee('Initech LLC')
        ->assertDontSee('Umbrella Ltd.')
        ->assertSee('Umbrella Group')
        ->assertSee('aria-label="Organization"', false)
        ->assertSee('data-breadcrumb', false)
        ->assertSee('data-breadcrumb-switcher', false)
        ->assertSee('data-breadcrumb-settings', false)
        ->assertSee('data-breadcrumb-create', false)
        ->assertDontSee('aria-label="Primary"', false)
        ->assertSee($Organization->enterprise->name)
        ->assertSee(OrganizationRoute::members->url([OrganizationRoute::organizationParameter => 'acme']))
        ->assertSee(OrganizationRoute::projects->url([OrganizationRoute::organizationParameter => 'acme']));

    expect(session(SessionKey::organization->value))->toBe('acme');

    // The address is what a request is for, so a session left pointing elsewhere
    // changes nothing about which organization a later visit resolves.
    $this->actingAs($User)
        ->withSession([SessionKey::organization->value => 'acme'])
        ->get(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'globex']))
        ->assertOk()
        ->assertSee('Globex Corp.')
        ->assertSee($Other->slug);

    // Existence is not public: a non-member is told there is nothing there.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'initech']))
        ->assertNotFound();

    $this->forgetCredentials()
        ->actingAs($User)
        ->get(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'initech']))
        ->assertNotFound();

    $this->actingAs($User)
        ->get(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => 'missing']))
        ->assertNotFound();

    expect($Stranger->slug)->toBe('initech');

    // The rail only stands inside an organization, and stands down everywhere else.
    // A depth the address has not reached contributes nothing to it, which is why
    // the connections of a project are absent until a project is being visited.
    $this->actingAs($User)->get($url)->assertOk();

    expect(OrganizationNav::visible())->toBeTrue()
        ->and(collect(OrganizationNav::items())->pluck('label')->all())
        ->toBe(['Overview', 'Projects', 'Members', 'Settings']);

    $Project = memberProject($Organization, [Projects::slug->value => 'alpha']);

    $this->actingAs($User)
        ->get(OrganizationRoute::project->url([
            OrganizationRoute::organizationParameter => 'acme',
            OrganizationRoute::projectParameter => 'alpha',
        ]))
        ->assertOk();

    expect(collect(OrganizationNav::items())->pluck('label')->all())
        ->toBe(['Overview', 'Projects', 'Connections', 'Members', 'Settings'])
        ->and($Project->organization_id)->toBe($Organization->id);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('aria-label="Organization"', false);

    expect(OrganizationNav::visible())->toBeFalse()
        ->and(OrganizationNav::items())->toBeEmpty();

    // The trail is one depth per thing the address resolved, widest first, and each
    // depth lists only what sits beside the thing it names.
    $this->actingAs($User)->get($url)->assertOk();

    $Breadcrumb = Breadcrumb::current() ?? throw new RuntimeException('An organization page carries a trail.');
    $trail = $Breadcrumb->trail();

    expect($trail)->toHaveCount(2)
        ->and($trail[0]->label)->toBe('Acme Holdings')
        ->and($trail[0]->url)->toBe(EnterpriseRoute::index->url([
            EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
        ]))
        ->and($trail[0]->settingsUrl)->toBe(EnterpriseRoute::settings->url([
            EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
        ]))
        ->and($trail[0]->createUrl)->toBe(EnterpriseRoute::create->url())
        ->and($trail[0]->fallback)->toBe(SvgName::city)
        ->and(collect($trail[0]->entries())->pluck('label')->all())
        ->toBe(['Umbrella Group'])
        ->and($trail[1]->label)->toBe('Acme Inc.')
        ->and($trail[1]->url)->toBe($url)
        ->and($trail[1]->settingsUrl)->toBe(OrganizationRoute::settings->url([
            OrganizationRoute::organizationParameter => 'acme',
        ]))
        ->and($trail[1]->createUrl)->toBe(EnterpriseRoute::organizationCreate->url([
            EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
        ]))
        ->and($trail[1]->fallback)->toBe(SvgName::building)
        // What a depth lists is what stands beside it: the thing being looked at is
        // the heading the list hangs from, so it is never also an entry in it.
        ->and(collect($trail[1]->entries())->pluck('label')->all())
        ->toBe(['Globex Corp.'])
        ->and(collect($trail[1]->entries())->pluck('fallback')->all())
        ->toBe([SvgName::building]);

    // A trail is only ever built for a reader the address resolved something for.
    $this->forgetCredentials();
    app()->instance('request', Request::create(Web::home->value));

    expect(Breadcrumb::current())->toBeNull();

    // A path naming no organization resolves none, and every reader of the context
    // is required to cope with that rather than assume one is always present.
    $Request = Request::create('/nowhere');
    app()->instance('request', $Request);

    $Passed = new ResolveOrganization()->handle($Request, static fn (): Response => new Response('passed'));

    expect($Passed->getContent())->toBe('passed')
        ->and(OrganizationContext::organization())->toBeNull()
        ->and(OrganizationContext::connection())->toBeNull()
        ->and(OrganizationContext::active())->toBeFalse()
        ->and(static fn (): Organization => Authorize::member())->toThrow(NotFoundHttpException::class)
        ->and(MembershipQuery::role($Organization, null))->toBeNull()
        ->and(MembershipQuery::held(User::factory()->createOne()))->toBeNull();

    // Membership reads the same way from either side of the pivot.
    expect(collect($User->organizations()->get())->pluck(Organizations::slug->value)->all())
        ->toEqualCanonicalizing(['acme', 'globex', 'umbrella']);

    // A member below the top still reads every page; standing only gates a change.
    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($url)
        ->assertOk()
        ->assertSee('Acme Inc.')
        ->assertDontSee('data-breadcrumb-settings', false);

    // Standing is what a settings door answers to, so a member below the top is
    // offered none — at either depth, because neither is held by membership alone.
    $Held = Breadcrumb::current();

    expect($Held)->not->toBeNull();

    $trail = $Held->trail();

    expect($trail[0]->settingsUrl)->toBeNull()
        ->and($trail[1]->settingsUrl)->toBeNull();
});
