<?php

use App\Helpers\Depth;
use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\MemberRole;
use App\Helpers\Picture;
use App\Helpers\Slug;
use App\Models\Project;
use App\Models\User;
use App\Modules\Contexts\DepthQuery;
use App\Modules\Memberships\MembershipQuery;
use App\Modules\Projects\ProjectForm;
use App\Modules\Projects\ProjectIconRequest;
use App\Routes\ContextRoute;
use App\Routes\Web;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a project is addressed inside its organization, named uniquely only there, and changed by the standing the organization grants', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $Owner = User::factory()->createOne();
    $Organization = memberOrganization($Owner, attributes: [
        Organizations::name->value => 'Acme Inc.',
        Organizations::slug->value => 'acme',
    ]);
    $parameters = atOrganization($Organization);
    $projects = ContextRoute::projectIndex->url($parameters);
    $create = ContextRoute::projectCreate->url($parameters);

    $this->get($create)->assertRedirect(Web::login->value);
    $this->post($projects, [ProjectForm::name => 'Website Redesign'])->assertRedirect(Web::login->value);

    $this->assertDatabaseCount(Projects::table(), 0);

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
        ->and($Project->creator?->id)->toBe($Owner->id)
        ->and($Project->iconUrl())->toBeNull()
        // Standing at the organization reaches the project, so nothing was granted here.
        ->and(MembershipQuery::held(Depth::project, $Project, $Owner))->toBeNull()
        ->and(MembershipQuery::effective(Depth::project, $Project, $Owner))->toBe(MemberRole::owner);

    $at = atProject($Project);
    $project = ContextRoute::project->url($at);
    $settings = ContextRoute::projectSettings->url($at);
    $icon = ContextRoute::projectIcon->url($at);

    $this->actingAs($Owner)
        ->get($project)
        ->assertOk()
        ->assertSee('Website Redesign')
        ->assertSee('Acme Inc.')
        ->assertSee($Owner->name)
        ->assertSee('data-project-settings', false)
        ->assertSee(ContextRoute::connectionIndex->url($at));

    // The segment is settled inside the organization, so the same name asked for twice
    // there gets a second one and a name reducing to nothing still gets one.
    foreach (['Website Redesign', '???'] as $name) {
        $this->actingAs($Owner)->from($create)->post($projects, [ProjectForm::name => $name]);
    }

    expect(collect(DepthQuery::children(Depth::project, $Organization, $Owner))->pluck(Projects::slug->value)->all())
        ->toEqualCanonicalizing(['website-redesign', 'website-redesign-2', Slug::fallback]);

    // A second organization may hold the same segment: a project is never addressed
    // without naming the organization around it.
    $Other = memberOrganization($Owner, attributes: [Organizations::slug->value => 'globex']);

    $this->actingAs($Owner)
        ->from(ContextRoute::projectCreate->url(atOrganization($Other)))
        ->post(ContextRoute::projectIndex->url(atOrganization($Other)), [ProjectForm::name => 'Website Redesign'])
        ->assertSessionHas('status', 'Project created.');

    expect(Project::query()->where(Projects::slug->value, 'website-redesign')->count())->toBe(2)
        ->and(DepthQuery::resolve(Depth::project, $Other, 'website-redesign', $Owner)?->getKey())
        ->not->toBe($Project->id);

    // The name and the icon answer to the standing the organization records.
    $this->actingAs($Owner)
        ->get($settings)
        ->assertOk()
        ->assertSee('data-project-form', false)
        ->assertSee('data-project-delete', false);

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
    $Disk->assertExists($first);

    expect($Project->iconUrl())->toBe(Disk::public->url($first))
        ->and(Picture::of($Project, Projects::icon, Directory::project_icons)->url())
        ->toBe(Disk::public->url($first));

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [ProjectIconRequest::icon => UploadedFile::fake()->image('second.jpg')]);

    $Disk->assertMissing($first);

    $this->actingAs($Owner)
        ->from($settings)
        ->post($icon, [ProjectIconRequest::icon => UploadedFile::fake()->create('notes.txt', 10)])
        ->assertSessionHasErrors(ProjectIconRequest::icon);

    $second = (string) $Project->refresh()->icon;

    $this->actingAs($Owner)->from($settings)->delete($icon)->assertSessionHas('status', 'Project icon removed.');

    $Disk->assertMissing($second);

    expect($Project->refresh()->icon)->toBeNull();

    // A standing granted at the project alone reaches the project and nothing above it.
    $Inside = User::factory()->createOne();
    MembershipQuery::grant(Depth::project, $Project, $Inside, MemberRole::admin);

    $this->forgetCredentials()
        ->actingAs($Inside)
        ->get($project)
        ->assertOk()
        ->assertSee('data-project-settings', false);

    $this->actingAs($Inside)
        ->from($settings)
        ->post($settings, [ProjectForm::name => 'Theirs'])
        ->assertSessionHas('status', 'Project updated.');

    $this->actingAs($Inside)->get($create)->assertForbidden();

    // Reading is the membership; changing is the standing.
    $Member = User::factory()->createOne();
    MembershipQuery::grant(Depth::organization, $Organization, $Member, MemberRole::member);

    $this->forgetCredentials()
        ->actingAs($Member)
        ->get($project)
        ->assertOk()
        ->assertDontSee('data-project-settings', false);

    $this->actingAs($Member)->get($create)->assertForbidden();
    $this->actingAs($Member)->get($settings)->assertForbidden();
    $this->actingAs($Member)->post($projects, [ProjectForm::name => 'Sneaky'])->assertForbidden();
    $this->actingAs($Member)->post($settings, [ProjectForm::name => 'Sneaky'])->assertForbidden();
    $this->actingAs($Member)->post($icon)->assertForbidden();
    $this->actingAs($Member)->delete($icon)->assertForbidden();
    $this->actingAs($Member)->delete($settings)->assertForbidden();

    // Existence is not public here either, and an unknown segment is not an error.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($project)
        ->assertNotFound();

    $this->actingAs($Owner)
        ->get(ContextRoute::project->url([...$parameters, ContextRoute::projectParameter => 'missing']))
        ->assertNotFound();

    $this->actingAs($Owner)
        ->from($settings)
        ->delete($settings)
        ->assertRedirect(ContextRoute::organization->url($parameters))
        ->assertSessionHas('status', 'Project deleted.');

    $this->assertDatabaseMissing(Projects::table(), [Projects::id->value => $Project->id]);

    expect(MembershipQuery::held(Depth::project, $Project, $Inside))->toBeNull();
});
