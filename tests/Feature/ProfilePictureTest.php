<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\OauthProviderId;
use App\Helpers\Picture;
use App\Helpers\ProfilePicture;
use App\Helpers\SessionKey;
use App\Models\User;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an uploaded picture outranks what the session cached', function (): void {
    $User = User::factory()->createOne([Users::picture->value => Directory::profile_pictures->value.'/face.jpg']);
    $this->actingAs($User);
    session([SessionKey::user_picture->value => 'https://example.com/avatar.jpg']);

    expect(ProfilePicture::current())
        ->toBe(Disk::public->url(Directory::profile_pictures->value.'/face.jpg'));
});

test('the session stands in while nothing was uploaded', function (): void {
    $this->actingAs(User::factory()->createOne());
    session([SessionKey::user_picture->value => 'https://example.com/avatar.jpg']);

    expect(ProfilePicture::current())->toBe('https://example.com/avatar.jpg');
});

test('nothing is shown when no source has a picture', function (): void {
    $this->actingAs(User::factory()->createOne());

    expect(ProfilePicture::current())->toBeNull()
        ->and(ProfilePicture::url(null))->toBeNull();
});

test('a picture is addressed by the column it is stored in', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $OauthProvider = $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => 'column-addressed',
        OauthProviders::name->value => $User->name,
        OauthProviders::given_name->value => 'Given',
        OauthProviders::family_name->value => 'Family',
        OauthProviders::picture->value => '',
        OauthProviders::email->value => $User->email,
        OauthProviders::email_verified->value => true,
        OauthProviders::id->value => 'column-addressed',
        OauthProviders::verified_email->value => true,
    ]);

    $Picture = Picture::of($OauthProvider, OauthProviders::picture, Directory::profile_pictures);
    $Picture->put(UploadedFile::fake()->image('logo.png'));

    $path = $OauthProvider->refresh()->picture;

    expect($path)->toStartWith(Directory::profile_pictures->value.'/')
        ->and($Picture->url())->toBe(Disk::public->url($path))
        ->and($User->refresh()->picture)->toBeNull();
    $Disk->assertExists($path);
});

test('clearing a picture discards the file the column points at', function (): void {
    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $Picture = ProfilePicture::of($User);
    $Picture->put(UploadedFile::fake()->image('face.png'));
    $path = (string) $User->refresh()->picture;

    $Picture->clear();

    expect($User->refresh()->picture)->toBeNull()
        ->and($Picture->url())->toBeNull();
    $Disk->assertMissing($path);
});
