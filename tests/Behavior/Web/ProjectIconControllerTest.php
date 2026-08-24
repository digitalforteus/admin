<?php

use App\Helpers\Disk;
use App\Http\Middleware\CanonicalizeUrl;
use App\Models\User;
use App\Modules\Projects\ProjectIconRequest;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Projects;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploading a project icon when storage does not retain it returns an error', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [Organizations::slug->value => 'acme']);
    $Project = memberProject($Organization, [Projects::slug->value => 'website-redesign']);
    $parameters = atProject($Project);

    $originalEnv = app()['env'];
    app()['env'] = 'production';
    $this->withoutMiddleware([CanonicalizeUrl::class, PreventRequestForgery::class]);

    config(['filesystems.default' => 'local']);

    try {
        $this->actingAs($User)
            ->from(ContextRoute::projectSettings->url($parameters))
            ->post(ContextRoute::projectIcon->url($parameters), [
                ProjectIconRequest::icon => UploadedFile::fake()->image('icon.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                ProjectIconRequest::icon => 'Uploading an icon needs a storage service that keeps it.',
            ]);
    } finally {
        app()['env'] = $originalEnv;
    }
});
