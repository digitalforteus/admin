<?php

use App\Helpers\Disk;
use App\Http\Middleware\CanonicalizeUrl;
use App\Models\User;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploading an icon when storage does not retain it returns an error', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User);

    $originalEnv = app()['env'];
    app()['env'] = 'production';
    $this->withoutMiddleware([CanonicalizeUrl::class, PreventRequestForgery::class]);

    config(['filesystems.default' => 'local']);

    try {
        $this->actingAs($User)
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
