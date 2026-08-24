<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\OrganizationRole;
use App\Helpers\Picture;
use App\Helpers\Slug;
use App\Helpers\SvgName;
use App\Http\Middleware\ResolveProject;
use App\Models\Project;
use App\Models\User;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Modules\Projects\ProjectForm;
use App\Modules\Projects\ProjectIconRequest;
use App\Modules\Projects\ProjectQuery;
use App\Routes\OrganizationRoute;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use App\View\DataModels\Breadcrumb;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('a project is addressed inside the organization that holds it, named uniquely only there, and changed by the standing the organization records', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $Owner = User::factory()->createOne();
    $Organization = memberOrganization($Owner, attributes: [
        Organizations::name->value => 'Acme Inc.',
        Organizations::slug->value => 'acme',
    ]);
    $parameters = [OrganizationRoute::organizationParameter => 'acme'];
    $projects = OrganizationRoute::projects->url($parameters);
    $create = OrganizationRoute::projectCreate->url($parameters);

    $this->get($projects)->assertRedirect(Web::login->value);
    $this->get($create)->assertRedirect(Web::login->value);
    $this->post($projects, [ProjectForm::name => 'Website Redesign'])->assertRedirect(Web::login->value);

    $this->assertDatabaseCount(Projects::table(), 0);

    $this->actingAs($Owner)
        ->get($projects)
        ->assertOk()
        ->assertSee('data-projects-empty', false)
        ->assertSee('data-project-add', false)
        ->assertSee($create);

    $this->actingAs($Owner)
        ->get($create)
        ->assertOk()
        ->assertSee('data-project-create', false)
        ->assertSee('Project Name');

    $this->actingAs($Owner)
        ->from($create)
        ->post($projects, [ProjectForm::name => '  Website   Redesign  '])
        ->assertSessionHas('status', 'Project created.');

    $Project = Project::query()->sole();

    expect($Project->name)->toBe('Website Redesign')
        ->and($Project->slug)->toBe('website-redesign')
        ->and($Project->organization_id)->toBe($Organization->id)
        ->and($Project->organization->id)->toBe($Organization->id)
        ->and($Project->created_by)->toBe($Owner->id)
        ->and($Project->creator?->id)->toBe($Owner->id)
        ->and($Project->icon)->toBeNull()
        ->and($Project->iconUrl())->toBeNull();

    $page = OrganizationRoute::project->url([
        ...$parameters,
        OrganizationRoute::projectParameter => 'website-redesign',
    ]);
    $settings = OrganizationRoute::projectSettings->url([
        ...$parameters,
        OrganizationRoute::projectParameter => 'website-redesign',
    ]);
    $icon = OrganizationRoute::projectIcon->url([
        ...$parameters,
        OrganizationRoute::projectParameter => 'website-redesign',
    ]);

    $this->actingAs($Owner)
        ->get($page)
        ->assertOk()
        ->assertSee('Website Redesign')
        ->assertSee('Acme Inc.')
        ->assertSee($Organization->enterprise->name)
        ->assertSee($Owner->name)
        ->assertSee('data-project-settings', false);

    // The url segment is settled inside the organization, so the same name asked for
    // twice there is given a second segment, and a name that reduces to nothing
    // still gets one.
    $this->actingAs($Owner)->from($create)->post($projects, [ProjectForm::name => 'Website Redesign']);
    $this->actingAs($Owner)->from($create)->post($projects, [ProjectForm::name => '???']);

    expect(collect(ProjectQuery::forOrganization($Organization))->pluck(Projects::slug->value)->all())
        ->toEqualCanonicalizing(['website-redesign', 'website-redesign-2', Slug::fallback]);

    // A second organization may hold the same segment, because a project is never
    // addressed without naming the organization around it.
    $Other = memberOrganization($Owner, attributes: [
        Organizations::enterprise_id->value => $Organization->enterprise_id,
        Organizations::name->value => 'Globex Corp.',
        Organizations::slug->value => 'globex',
    ]);
    $otherParameters = [OrganizationRoute::organizationParameter => 'globex'];

    $this->actingAs($Owner)
        ->from(OrganizationRoute::projectCreate->url($otherParameters))
        ->post(OrganizationRoute::projects->url($otherParameters), [ProjectForm::name => 'Website Redesign'])
        ->assertSessionHas('status', 'Project created.');

    expect(collect(ProjectQuery::forOrganization($Other))->pluck(Projects::slug->value)->all())
        ->toBe(['website-redesign'])
        ->and(Project::query()->where(Projects::slug->value, 'website-redesign')->count())->toBe(2);

    // Scoping is the whole of resolving one: the other organization's project is not
    // served under this organization's address, and an unknown one is not an error.
    $Elsewhere = ProjectQuery::find($Other, 'website-redesign');

    expect($Elsewhere->id)->not->toBe($Project->id)
        ->and(ProjectQuery::bySlug($Organization, 'website-redesign')?->id)->toBe($Project->id)
        ->and(ProjectQuery::bySlug($Other, Slug::fallback))->toBeNull();

    $this->actingAs($Owner)
        ->get(OrganizationRoute::project->url([
            ...$otherParameters,
            OrganizationRoute::projectParameter => Slug::fallback,
        ]))
        ->assertRedirect(OrganizationRoute::projects->url($otherParameters));

    // A write names its project directly rather than reading a resolved one, so it
    // answers for itself when the organization holds no such segment.
    expect(static fn (): Project => ProjectQuery::find($Other, 'website-redesign-2'))
        ->toThrow(NotFoundHttpException::class);

    // The trail gains the project depth, listing the ones beside it in the same
    // organization and never itself.
    $this->actingAs($Owner)->get($page)->assertOk();

    $trail = (Breadcrumb::current() ?? throw new RuntimeException('A project page carries a trail.'))->trail();

    expect($trail)->toHaveCount(4)
        ->and($trail[3]->settled())->toBeFalse()
        ->and($trail[3]->label)->toBe('Select connection')
        ->and($trail[2]->label)->toBe('Website Redesign')
        ->and($trail[2]->url)->toBe($page)
        ->and($trail[2]->fallback)->toBe(SvgName::folder)
        ->and($trail[2]->settingsUrl)->toBe($settings)
        ->and($trail[2]->createUrl)->toBe($create)
        ->and(collect($trail[2]->entries())->pluck('label')->all())
        ->toEqualCanonicalizing(['Website Redesign', '???'])
        ->and(collect($trail[2]->entries())->pluck('fallback')->all())
        ->toBe([SvgName::folder, SvgName::folder]);

    // The name is changed and the icon is kept by the same standing, and replacing
    // an icon discards the file it replaces because nothing else ever will.
    $this->actingAs($Owner)
        ->get($settings)
        ->assertOk()
        ->assertSee('data-project-form', false)
        ->assertSee('data-project-delete', false)
        ->assertSee('Website Redesign');

    $this->actingAs($Owner)
        ->from($settings)
        ->post($settings, [ProjectForm::name => 'Site Refresh'])
        ->assertSessionHas('status', 'Project updated.');

    expect($Project->refresh()->name)->toBe('Site Refresh');

    $this->actingAs($Owner)
        ->from($settings)
        ->post($settings, [ProjectForm::name => ''])
        ->assertSessionHasErrors(ProjectForm::name);

    $this->actingAs($Owner)
        ->from($create)
        ->post($projects, [ProjectForm::name => str_repeat('a', 256)])
        ->assertSessionHasErrors(ProjectForm::name)
        ->assertSessionHasInput(ProjectForm::name, str_repeat('a', 256));

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [ProjectIconRequest::icon => UploadedFile::fake()->image('first.jpg')])
        ->assertSessionHas('status', 'Project icon updated.');

    $first = (string) $Project->refresh()->icon;

    expect($first)->toStartWith(Directory::project_icons->value.'/')
        ->and($Project->iconUrl())->toBe(Disk::public->url($first))
        ->and(Picture::of($Project, Projects::icon, Directory::project_icons)->url())
        ->toBe(Disk::public->url($first));

    $Disk->assertExists($first);

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [ProjectIconRequest::icon => UploadedFile::fake()->image('second.jpg')]);

    $Disk->assertMissing($first);

    $second = (string) $Project->refresh()->icon;

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [ProjectIconRequest::icon => UploadedFile::fake()->create('notes.txt', 10)])
        ->assertSessionHasErrors(ProjectIconRequest::icon);

    $this->actingAs($Owner)
        ->from($settings)
        ->delete($icon)
        ->assertSessionHas('status', 'Project icon removed.');

    $Disk->assertMissing($second);

    expect($Project->refresh()->icon)->toBeNull();

    // Reading is the membership; changing is the standing the organization records.
    $Member = User::factory()->createOne();
    MembershipQuery::add($Organization, $Member, OrganizationRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($projects)
        ->assertOk()
        ->assertDontSee('data-project-add', false);

    $this->actingAs($Member)
        ->get($page)
        ->assertOk()
        ->assertDontSee('data-project-settings', false)
        ->assertDontSee('data-breadcrumb-settings', false);

    $Reading = Breadcrumb::current();

    expect($Reading)->not->toBeNull();

    $held = $Reading->trail();

    expect($held[2]->settingsUrl)->toBeNull()
        ->and($held[2]->createUrl)->toBeNull()
        ->and($held[3]->createAction)->toBeNull();

    $this->actingAs($Member)->get($create)->assertForbidden();
    $this->actingAs($Member)->get($settings)->assertForbidden();
    $this->actingAs($Member)->post($projects, [ProjectForm::name => 'Sneaky'])->assertForbidden();
    $this->actingAs($Member)->post($settings, [ProjectForm::name => 'Sneaky'])->assertForbidden();
    $this->actingAs($Member)->post($icon)->assertForbidden();
    $this->actingAs($Member)->delete($icon)->assertForbidden();
    $this->actingAs($Member)->delete($settings)->assertForbidden();

    // Existence is not public here either.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($page)
        ->assertNotFound();

    $this->actingAs($Owner)
        ->from($settings)
        ->delete($settings)
        ->assertRedirect($projects)
        ->assertSessionHas('status', 'Project deleted.');

    $this->assertDatabaseMissing(Projects::table(), [Projects::id->value => $Project->id]);

    // Deleting the organization takes its projects with it: nothing else would.
    expect(ProjectQuery::forOrganization($Other))->toHaveCount(1);

    $Other->delete();

    expect(ProjectQuery::forOrganization($Other))->toBeEmpty();

    // A path naming no project resolves none, and every reader of the context is
    // required to cope with that rather than assume one is always present.
    $this->forgetCredentials();

    $Request = Request::create('/nowhere');
    app()->instance('request', $Request);

    $Passed = new ResolveProject()->handle($Request, static fn (): Response => new Response('passed'));

    expect($Passed->getContent())->toBe('passed')
        ->and(OrganizationContext::project())->toBeNull();
});
