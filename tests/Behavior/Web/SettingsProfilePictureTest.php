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

test('the page renders the profile picture control', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-picture-field', false)
        ->assertSee('Upload a photo...')
        ->assertSee('Remove photo')
        ->assertSee(Auth::settingsProfilePicture->value);
});

test('a user uploads a profile picture', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('face.jpg'),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile picture updated.');

    $path = $User->refresh()->picture;

    expect($path)->toStartWith(Directory::profile_pictures->value.'/');
    $Disk->assertExists((string) $path);
});

test('uploading a profile picture discards the one it replaces', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('first.jpg'),
        ]);

    $first = (string) $User->refresh()->picture;

    $this->actingAs($User)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('second.jpg'),
        ]);

    $second = (string) $User->refresh()->picture;

    expect($second)->not->toBe($first);
    $Disk->assertMissing($first);
    $Disk->assertExists($second);
});

test('a user removes their profile picture', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('face.jpg'),
        ]);

    $path = (string) $User->refresh()->picture;

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->delete(Auth::settingsProfilePicture->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHas('status', 'Profile picture removed.');

    expect($User->refresh()->picture)->toBeNull();
    $Disk->assertMissing($path);
});

test('a file that is not an image is rejected', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->create('resume.pdf', 16, 'application/pdf'),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);

    expect($User->refresh()->picture)->toBeNull();
});

test('an image larger than the limit is rejected', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value, [
            ProfilePictureRequest::picture => UploadedFile::fake()->image('huge.jpg')->size(Picture::kilobytes + 1),
        ])
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);

    expect($User->refresh()->picture)->toBeNull();
});

test('a missing file is rejected', function (): void {
    $User = User::factory()->createOne();

    $this->actingAs($User)
        ->from(Auth::settingsProfile->value)
        ->post(Auth::settingsProfilePicture->value)
        ->assertRedirect(Auth::settingsProfile->value)
        ->assertSessionHasErrors(ProfilePictureRequest::picture);
});

test('an uploaded picture is preferred over the one the provider supplied', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    oauthPicture($User, 'https://example.com/provider.jpg');

    expect(ProfilePicture::url($User))->toBe('https://example.com/provider.jpg');

    $User->update([Users::picture->value => Directory::profile_pictures->value.'/uploaded.jpg']);

    expect(ProfilePicture::url($User->refresh()))
        ->toBe(Disk::public->url(Directory::profile_pictures->value.'/uploaded.jpg'));
});

test('the provider picture is used when nothing was uploaded', function (): void {
    $User = User::factory()->createOne();
    oauthPicture($User, 'https://example.com/provider.jpg');

    $this->actingAs($User)
        ->get(Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('https://example.com/provider.jpg', false);
});

test('production without a storage service that keeps the file offers no upload', function (): void {
    app()->instance('env', 'production');
    Config::set('filesystems.default', Disk::ephemeral);

    $this->actingAs(User::factory()->createOne())
        ->get('https://localhost'.Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('menu-disabled', false)
        ->assertDontSee('data-picture-field-form', false);
});

test('production without a storage service that keeps the file refuses an upload', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    app()->instance('env', 'production');
    Config::set('filesystems.default', Disk::ephemeral);
    $User = User::factory()->createOne();

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
});

test('production storing where the file is kept offers the upload', function (): void {
    app()->instance('env', 'production');
    Config::set('filesystems.default', 's3');

    $this->actingAs(User::factory()->createOne())
        ->get('https://localhost'.Auth::settingsProfile->value)
        ->assertOk()
        ->assertSee('data-picture-field-form', false);
});
