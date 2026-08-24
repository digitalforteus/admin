<?php

use App\Helpers\OrganizationRole;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Projects\ProjectForm;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\EnterpriseRoute;
use App\Routes\OrganizationRoute;
use App\Routes\Web;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use App\View\DataModels\Breadcrumb;
use App\View\DataModels\BreadcrumbField;
use App\View\DataModels\BreadcrumbSegment;
use App\View\DataModels\TextInput;

/**
 * The trail as the reader on this request is offered it.
 *
 * @return list<BreadcrumbSegment>
 */
function readerTrail(): array
{
    return (Breadcrumb::current() ?? throw new RuntimeException('A reader is offered a trail.'))->trail();
}

test('the trail offers the deepest depth the address left unsettled, listing what could be picked and taking the one form that would settle it', function (): void {
    // A stranger is offered nothing: there is no reader to pick for.
    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-breadcrumb', false);

    $User = User::factory()->createOne();

    // An account holding nothing still gets the widest depth, unsettled: the trail is
    // built for a reader rather than for an address.
    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('data-breadcrumb', false)
        ->assertSee('data-breadcrumb-unsettled', false)
        ->assertSee('Select enterprise')
        ->assertSee('data-breadcrumb-form', false)
        ->assertSee(EnterpriseRoute::create->url())
        ->assertDontSee('data-breadcrumb-settings', false);

    $trail = readerTrail();

    expect($trail)->toHaveCount(1)
        ->and($trail[0]->settled())->toBeFalse()
        ->and($trail[0]->url)->toBeNull()
        ->and($trail[0]->settingsUrl)->toBeNull()
        ->and($trail[0]->createUrl)->toBeNull()
        ->and($trail[0]->createAction)->toBe(EnterpriseRoute::create->url())
        ->and($trail[0]->entries())->toBeEmpty()
        ->and(collect($trail[0]->fields())->pluck('name')->all())
        ->toBe([EnterpriseForm::name, EnterpriseForm::organization]);

    // The inline form names the fields the write already validates, so the trail and
    // the create page submit the same request.
    $Field = $trail[0]->fields()[0];
    $TextInput = TextInput::from(EnterpriseForm::textInput(EnterpriseForm::name));

    expect($Field->label)->toBe($TextInput->legend)
        ->and($Field->placeholder)->toBe($TextInput->placeholder)
        ->and(BreadcrumbField::of([TextInput::name => 'bare']))
        ->toBe([
            BreadcrumbField::name => 'bare',
            BreadcrumbField::label => 'bare',
            BreadcrumbField::placeholder => '',
        ]);

    // A rejected write comes back to the page that sent it, and the message is found
    // in the form that sent it rather than lost with the dropdown that closed.
    $this->actingAs($User)
        ->from(Web::home->value)
        ->post(EnterpriseRoute::create->url(), [EnterpriseForm::name => '', EnterpriseForm::organization => ''])
        ->assertRedirect(Web::home->value);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('The name field is required.');

    // Taking the form settles the depth and lands the reader inside what it made.
    $this->actingAs($User)
        ->from(Web::home->value)
        ->post(EnterpriseRoute::create->url(), [
            EnterpriseForm::name => 'Acme Holdings',
            EnterpriseForm::organization => 'Acme Inc.',
        ])
        ->assertSessionHas('status', 'Enterprise created.');

    $Enterprise = Enterprise::query()->sole();
    $Organization = Organization::query()->sole();

    // With one enterprise settled elsewhere, the home page lists it as a choice.
    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('Select enterprise')
        ->assertSee('Acme Holdings')
        ->assertSee(EnterpriseRoute::index->url([
            EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
        ]));

    $trail = readerTrail();

    expect(collect($trail[0]->entries())->pluck('label')->all())->toBe(['Acme Holdings']);

    // One depth settled leaves the next one unsettled, and no depth below that: the
    // one after has nothing to scope its list by.
    $this->actingAs($User)
        ->get(EnterpriseRoute::index->url([EnterpriseRoute::enterpriseParameter => $Enterprise->slug]))
        ->assertOk()
        ->assertSee('Select organization')
        ->assertSee('data-breadcrumb-form', false)
        ->assertDontSee('Select project');

    $trail = readerTrail();

    expect($trail)->toHaveCount(2)
        ->and($trail[0]->settled())->toBeTrue()
        ->and($trail[1]->settled())->toBeFalse()
        ->and($trail[1]->label)->toBe('Select organization')
        ->and($trail[1]->createAction)->toBe(EnterpriseRoute::organizationCreate->url([
            EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
        ]))
        ->and(collect($trail[1]->fields())->pluck('name')->all())->toBe([OrganizationForm::name])
        ->and(collect($trail[1]->entries())->pluck('label')->all())->toBe(['Acme Inc.']);

    // The organization depth takes its own form the same way.
    $this->actingAs($User)
        ->from(EnterpriseRoute::index->url([EnterpriseRoute::enterpriseParameter => $Enterprise->slug]))
        ->post(EnterpriseRoute::organizationCreate->url([
            EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
        ]), [OrganizationForm::name => 'Globex Corp.'])
        ->assertSessionHas('status', 'Organization created.');

    expect(Organization::query()->count())->toBe(2);

    // Inside an organization the project depth is what is left to choose.
    $organization = OrganizationRoute::index->url([
        OrganizationRoute::organizationParameter => $Organization->slug,
    ]);

    $this->actingAs($User)
        ->get($organization)
        ->assertOk()
        ->assertSee('Select project')
        ->assertSee('data-breadcrumb-form', false)
        ->assertDontSee('Select connection');

    $trail = readerTrail();

    expect($trail)->toHaveCount(3)
        ->and($trail[2]->label)->toBe('Select project')
        ->and($trail[2]->createAction)->toBe(OrganizationRoute::projects->url([
            OrganizationRoute::organizationParameter => $Organization->slug,
        ]))
        ->and(collect($trail[2]->fields())->pluck('name')->all())->toBe([ProjectForm::name])
        ->and($trail[2]->entries())->toBeEmpty();

    $this->actingAs($User)
        ->from($organization)
        ->post(OrganizationRoute::projects->url([
            OrganizationRoute::organizationParameter => $Organization->slug,
        ]), [ProjectForm::name => 'Website Redesign'])
        ->assertSessionHas('status', 'Project created.');

    $Project = Project::query()->sole();

    // Inside a project the connection depth is offered as a link and never a form:
    // the credentials a connection holds are not one box.
    $project = OrganizationRoute::project->url([
        OrganizationRoute::organizationParameter => $Organization->slug,
        OrganizationRoute::projectParameter => $Project->slug,
    ]);
    $create = OrganizationRoute::connectionCreate->url([
        OrganizationRoute::organizationParameter => $Organization->slug,
        OrganizationRoute::projectParameter => $Project->slug,
    ]);

    $this->actingAs($User)
        ->get($project)
        ->assertOk()
        ->assertSee('Select connection')
        ->assertSee($create);

    $trail = readerTrail();

    expect($trail)->toHaveCount(4)
        ->and($trail[3]->label)->toBe('Select connection')
        ->and($trail[3]->createAction)->toBeNull()
        ->and($trail[3]->fields())->toBeEmpty()
        ->and($trail[3]->createUrl)->toBe($create)
        ->and($trail[3]->entries())->toBeEmpty();

    // A form the reader may not submit is not offered: the standing that gates the
    // write gates the box, because the markup cannot ask.
    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($organization)
        ->assertOk()
        ->assertSee('Select project')
        ->assertDontSee('data-breadcrumb-form', false);

    $held = readerTrail();

    expect($held[2]->createAction)->toBeNull()
        ->and(collect($held[2]->entries())->pluck('label')->all())->toBe(['Website Redesign']);

    $this->actingAs($Member)
        ->get($project)
        ->assertOk()
        ->assertDontSee($create);

    $held = readerTrail();

    expect($held[3]->createUrl)->toBeNull()
        ->and($held[3]->createAction)->toBeNull();

    // An unsettled depth is a segment with no destination, which is the whole of what
    // marks it: nothing else about it is required.
    expect(BreadcrumbSegment::from([
        BreadcrumbSegment::label => 'Select enterprise',
        BreadcrumbSegment::fallback => $trail[0]->fallback,
    ])->settled())->toBeFalse()
        ->and($Enterprise->getAttribute(Enterprises::name->value))->toBe('Acme Holdings')
        ->and($Organization->getAttribute(Organizations::name->value))->toBe('Acme Inc.')
        ->and($Project->getAttribute(Projects::name->value))->toBe('Website Redesign');
});
