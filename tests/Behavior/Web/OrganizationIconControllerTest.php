<?php

use App\Helpers\Disk;
use App\Http\Middleware\CanonicalizeUrl;
use App\Models\User;
use App\Modules\Organizations\Organizations\OrganizationIconRequest;
use App\Routes\ContextRoute;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploading an organization icon when storage does not retain it returns an error', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);
    $parameters = atOrganization($Organization);

    $originalEnv = app()['env'];
    app()['env'] = 'production';
    $this->withoutMiddleware([CanonicalizeUrl::class, PreventRequestForgery::class]);

    config(['filesystems.default' => 'local']);

    try {
        $this->actingAs($User)
            ->from(ContextRoute::organizationSettings->url($parameters))
            ->post(ContextRoute::organizationIcon->url($parameters), [
                OrganizationIconRequest::icon => UploadedFile::fake()->image('icon.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                OrganizationIconRequest::icon => 'Uploading an icon needs a storage service that keeps it.',
            ]);
    } finally {
        app()['env'] = $originalEnv;
    }
});
