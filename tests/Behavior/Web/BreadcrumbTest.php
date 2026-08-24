<?php

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Memberships\MembershipQuery;
use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Modules\Projects\ProjectForm;
use App\Routes\ContextRoute;
use App\Routes\Web;
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

test('the trail is one segment per depth the address settled, plus the deepest one it left open, and offers the form that would settle it', function (): void {
    $this->get(Web::home->value)->assertOk()->assertDontSee('data-breadcrumb', false);

    $User = User::factory()->createOne();

    // An account holding nothing still gets the widest depth, unsettled.
    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('data-breadcrumb', false)
        ->assertSee('data-breadcrumb-unsettled', false)
        ->assertSee('Select enterprise')
        ->assertSee('data-breadcrumb-form', false)
        ->assertDontSee('data-breadcrumb-settings', false);

    $trail = readerTrail();

    expect($trail)->toHaveCount(1)
        ->and($trail[0]->settled())->toBeFalse()
        ->and($trail[0]->url)->toBeNull()
        ->and($trail[0]->settingsUrl)->toBeNull()
        ->and($trail[0]->createAction)->toBe(ContextRoute::enterpriseIndex->url())
        ->and($trail[0]->entries())->toBeEmpty()
        ->and(collect($trail[0]->fields())->pluck('name')->all())->toBe([EnterpriseForm::name])
        ->and(BreadcrumbField::of([TextInput::name => 'bare']))
        ->toBe([
            BreadcrumbField::name => 'bare',
            BreadcrumbField::label => 'bare',
            BreadcrumbField::placeholder => '',
        ]);

    // Taking the form settles the depth and lands the reader inside what it made.
    $this->actingAs($User)
        ->from(Web::home->value)
        ->post(ContextRoute::enterpriseIndex->url(), [EnterpriseForm::name => ''])
        ->assertRedirect(Web::home->value);

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('The name field is required.');

    $this->actingAs($User)
        ->from(Web::home->value)
        ->post(ContextRoute::enterpriseIndex->url(), [EnterpriseForm::name => 'Acme Holdings'])
        ->assertSessionHas('status', 'Enterprise created.');

    $Enterprise = Enterprise::query()->sole();

    $this->actingAs($User)
        ->get(Web::home->value)
        ->assertOk()
        ->assertSee('Select enterprise')
        ->assertSee('Acme Holdings');

    expect(collect(readerTrail()[0]->entries())->pluck('label')->all())->toBe(['Acme Holdings']);

    // One depth settled leaves the next one open, and no depth below that: the one
    // after has nothing to scope its list by.
    $this->actingAs($User)
        ->get(ContextRoute::enterprise->url(atEnterprise($Enterprise)))
        ->assertOk()
        ->assertSee('Select organization')
        ->assertSee('data-breadcrumb-form', false)
        ->assertSee('data-breadcrumb-settings', false)
        ->assertDontSee('Select project');

    $trail = readerTrail();

    expect($trail)->toHaveCount(2)
        ->and($trail[0]->settled())->toBeTrue()
        ->and($trail[0]->label)->toBe('Acme Holdings')
        ->and($trail[0]->settingsUrl)->toBe(ContextRoute::enterpriseSettings->url(atEnterprise($Enterprise)))
        ->and($trail[0]->entries())->toBeEmpty()
        ->and($trail[1]->settled())->toBeFalse()
        ->and($trail[1]->label)->toBe('Select organization')
        ->and($trail[1]->createAction)->toBe(ContextRoute::organizationIndex->url(atEnterprise($Enterprise)))
        ->and(collect($trail[1]->fields())->pluck('name')->all())->toBe([OrganizationForm::name]);

    $this->actingAs($User)
        ->from(ContextRoute::enterprise->url(atEnterprise($Enterprise)))
        ->post(ContextRoute::organizationIndex->url(atEnterprise($Enterprise)), [
            OrganizationForm::name => 'Acme Inc.',
        ])
        ->assertSessionHas('status', 'Organization created.');

    $Organization = Organization::query()->sole();
    $organization = ContextRoute::organization->url(atOrganization($Organization));

    $this->actingAs($User)
        ->get($organization)
        ->assertOk()
        ->assertSee('Select project')
        ->assertDontSee('Select connection');

    $trail = readerTrail();

    expect($trail)->toHaveCount(3)
        ->and($trail[1]->label)->toBe('Acme Inc.')
        ->and($trail[2]->label)->toBe('Select project')
        ->and($trail[2]->createAction)->toBe(ContextRoute::projectIndex->url(atOrganization($Organization)))
        ->and(collect($trail[2]->fields())->pluck('name')->all())->toBe([ProjectForm::name]);

    $this->actingAs($User)
        ->from($organization)
        ->post(ContextRoute::projectIndex->url(atOrganization($Organization)), [
            ProjectForm::name => 'Website Redesign',
        ])
        ->assertSessionHas('status', 'Project created.');

    $Project = Project::query()->sole();
    $project = ContextRoute::project->url(atProject($Project));
    $create = ContextRoute::connectionCreate->url(atProject($Project));

    // Inside a project the connection depth is offered as a link and never a form:
    // the credentials a connection holds are not one box.
    $this->actingAs($User)
        ->get($project)
        ->assertOk()
        ->assertSee('Select connection')
        ->assertSee($create);

    $trail = readerTrail();

    expect($trail)->toHaveCount(4)
        ->and($trail[2]->label)->toBe('Website Redesign')
        ->and($trail[3]->label)->toBe('Select connection')
        ->and($trail[3]->createAction)->toBeNull()
        ->and($trail[3]->fields())->toBeEmpty()
        ->and($trail[3]->createUrl)->toBe($create);

    // Naming a connection settles the fourth depth, and nothing is left open below it.
    $Connection = projectConnection($Project, attributes: [
        Organizations::name->value => 'Primary Repo',
        Projects::slug->value => 'primary-repo',
    ]);

    $this->actingAs($User)
        ->get(ContextRoute::connection->url([
            ...atProject($Project),
            ContextRoute::connectionParameter => $Connection->slug,
        ]))
        ->assertOk();

    $trail = readerTrail();

    expect($trail)->toHaveCount(4)
        ->and($trail[3]->settled())->toBeTrue()
        ->and($trail[3]->label)->toBe($Connection->name);

    // A form the reader may not submit is not offered: the standing that gates the
    // write gates the box, because the markup cannot ask.
    $Member = User::factory()->createOne();
    MembershipQuery::grant(Depth::organization, $Organization, $Member, MemberRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($organization)
        ->assertOk()
        ->assertSee('Select project')
        ->assertDontSee('data-breadcrumb-form', false)
        ->assertDontSee('data-breadcrumb-settings', false);

    $held = readerTrail();

    expect($held[0]->settingsUrl)->toBeNull()
        ->and($held[1]->settingsUrl)->toBeNull()
        ->and($held[2]->createAction)->toBeNull()
        ->and(collect($held[2]->entries())->pluck('label')->all())->toBe(['Website Redesign']);

    $this->actingAs($Member)->get($project)->assertOk();

    expect(readerTrail()[3]->createUrl)->toBeNull();

    // An unsettled depth is a segment with no destination, which is the whole of what
    // marks it.
    expect(BreadcrumbSegment::from([
        BreadcrumbSegment::label => 'Select enterprise',
        BreadcrumbSegment::fallback => Depth::enterprise->icon(),
    ])->settled())->toBeFalse();
});
