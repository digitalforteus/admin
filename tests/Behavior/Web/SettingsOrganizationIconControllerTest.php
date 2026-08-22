<?php

use App\Helpers\Disk;
use App\Http\Middleware\CanonicalizeUrl;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploading an icon when storage does not retain it returns an error', function (): void {
    Storage::fake(Disk::public->value);
    $Organization = Organization::factory()->createOne();

    // Disk::retains() treats non-production as always retaining, so this path
    // only exercises under app()->isProduction() === true. Flipping that also
    // turns off two unrelated behaviors that key off the same 'testing' env
    // check: CanonicalizeUrl's https redirect, and the CSRF bypass tests rely
    // on — both excluded here since neither is what this test is about.
    $originalEnv = app()['env'];
    app()['env'] = 'production';
    $this->withoutMiddleware([CanonicalizeUrl::class, PreventRequestForgery::class]);

    // Set filesystems to use local (ephemeral) driver
    config(['filesystems.default' => 'local']);

    try {
        $this->actingAs(User::factory()->createOne())
            ->from(Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]))
            ->post(Auth::settingsOrganizationIcon->url([Auth::organizationParameter => $Organization->id]), [
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
