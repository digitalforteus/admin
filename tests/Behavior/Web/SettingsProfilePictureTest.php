<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\OauthProviderId;
use App\Helpers\Picture;
use App\Helpers\ProfilePicture;
use App\Models\User;
use App\Modules\Settings\Profile\ProfilePictureRequest;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

function oauthPicture(User $User, string $picture): void
{
    $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => 'picture-'.$User->id,
        OauthProviders::name->value => $User->name,
        OauthProviders::given_name->value => 'Given',
        OauthProviders::family_name->value => 'Family',
        OauthProviders::picture->value => $picture,
        OauthProviders::email->value => $User->email,
        OauthProviders::email_verified->value => true,
        OauthProviders::id->value => 'picture-'.$User->id,
        OauthProviders::verified_email->value => true,
    ]);
}

test('guests cannot upload or remove a profile picture', function (): void {
    $this->post(Auth::settingsProfilePicture->value)->assertRedirect(Web::login->value);
    $this->delete(Auth::settingsProfilePicture->value)->assertRedirect(Web::login->value);
});

test('the control renders, and an uploaded picture is preferred over the one the provider supplied', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    oauthPicture($User, 'https://example.com/provider.jpg');

    expect(ProfilePicture::url($User))->toBe('https://example.com/provider.jpg');

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-picture-field', false)
        ->assertSee('Upload a photo...')
        ->assertSee('Remove photo')
        ->assertSee(Auth::settingsProfilePicture->value)
        ->assertSee('https://example.com/provider.jpg', false);

    $User->update([Users::picture->value => Directory::profile_pictures->value.'/uploaded.jpg']);

    expect(ProfilePicture::url($User->refresh()))
        ->toBe(Disk::public->url(Directory::profile_pictures->value.'/uploaded.jpg'));
});

test('a picture is uploaded, replaces the one before it, and is removed', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('first.jpg'),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile picture updated.');

    $first = (string) $User->refresh()->picture;

    expect($first)->toStartWith(Directory::profile_pictures->value.'/');
    $Disk->assertExists($first);

    $this->actingAs($User)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('second.jpg'),
        ]);

    $second = (string) $User->refresh()->picture;

    expect($second)->not->toBe($first);
    $Disk->assertMissing($first);
    $Disk->assertExists($second);

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->delete(Auth::settingsProfilePicture->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile picture removed.');

    expect($User->refresh()->picture)->toBeNull();
    $Disk->assertMissing($second);
});

test('an upload that is not an image, is over the limit, or is missing altogether is refused', function (): void {
    Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    foreach ([
        UploadedFile::fake()->create('resume.pdf', 16, 'application/pdf'),
        UploadedFile::fake()->image('huge.jpg')->size(Picture::kilobytes + 1),
    ] as $File) {
        $this->actingAs($User)
            ->from(Auth::settingsProfile->value)
            ->post(Auth::settingsProfilePicture->value, [
                ProfilePictureRequest::picture => $File,
            ])
            ->assertRedirect(Auth::settingsProfile->value)
            ->assertSessionHasErrors(ProfilePictureRequest::picture);

        expect($User->refresh()->picture)->toBeNull();
    }

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);

    expect($User->refresh()->picture)->toBeNull();
});

test('production offers the upload only where the file is kept', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    app()->instance('env', 'production');
    Config::set('filesystems.default', Disk::ephemeral);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->get('https://localhost'.Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('menu-disabled', false)
        ->assertDontSee('data-picture-field-form', false);

    $this->actingAs($User)
        ->withoutMiddleware(PreventRequestForgery::class)
        ->from(Auth::settingsProfile->value)
        ->post('https://localhost'.Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('face.jpg'),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);

    expect($User->refresh()->picture)->toBeNull()
        ->and($Disk->allFiles())->toBeEmpty();

    Config::set('filesystems.default', 's3');

    $this->actingAs($User)
        ->get('https://localhost'.Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-picture-field-form', false);
});
