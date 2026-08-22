<?php

use App\Helpers\OrganizationRole;
use App\Helpers\SessionKey;
use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\Authorize;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Routes\OrganizationRoute;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use App\View\DataModels\OrganizationNav;
use App\View\DataModels\OrganizationSwitcher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('an organization page is addressed by slug, scoped to membership, and carries the chrome for the context it names', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [
        Organizations::name->value => 'Acme Inc.',
        Organizations::slug->value => 'acme',
    ]);
    $Other = memberOrganization($User, attributes: [
        Organizations::name->value => 'Globex Corp.',
        Organizations::slug->value => 'globex',
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
        ->assertSee('aria-label="Organization"', false)
        ->assertSee('data-organization-switcher', false)
        ->assertSee('data-connection-breadcrumb', false)
        ->assertDontSee('data-connection-switcher', false)
        ->assertDontSee('aria-label="Primary"', false)
        ->assertSee($Organization->enterprise->name)
        ->assertSee(OrganizationRoute::members->url([OrganizationRoute::organizationParameter => 'acme']))
        ->assertSee(OrganizationRoute::connections->url([OrganizationRoute::organizationParameter => 'acme']));

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
    $this->actingAs($User)->get($url)->assertOk();

    expect(OrganizationNav::visible())->toBeTrue()
        ->and(collect(OrganizationNav::items())->pluck('label')->all())
        ->toBe(['Overview', 'Connections', 'Members']);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('aria-label="Organization"', false);

    expect(OrganizationNav::visible())->toBeFalse()
        ->and(OrganizationNav::items())->toBeEmpty();

    // Membership is what the switcher lists, and the address is what it checks.
    $this->actingAs($User)->get($url)->assertOk();

    $Switcher = OrganizationSwitcher::current() ?? throw new RuntimeException('An organization page carries a switcher.');

    expect($Switcher->name)->toBe('Acme Inc.')
        ->and($Switcher->slug)->toBe('acme')
        ->and($Switcher->enterprise)->toBe($Organization->enterprise->name);

    $labels = [];
    $active = [];

    foreach ($Switcher->sections() as $Group) {
        foreach ($Group->items() as $NavItem) {
            $labels[] = $NavItem->label;
            $active[$NavItem->label] = $Group->isActive($NavItem);
        }
    }

    expect($labels)->toContain('Acme Inc.', 'Globex Corp.')
        ->and($labels)->not->toContain('Initech LLC')
        ->and($active['Acme Inc.'])->toBeTrue()
        ->and($active['Globex Corp.'])->toBeFalse();

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
        ->toEqualCanonicalizing(['acme', 'globex']);

    // A member below the top still reads every page; standing only gates a change.
    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($url)
        ->assertOk()
        ->assertSee('Acme Inc.');
});
