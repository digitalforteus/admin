<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\ProfilePicture as Picture;
use App\Helpers\SessionKey;
use App\Models\User;
use App\Sources\Db\App\Users;
use App\View\DataModels\ProfilePicture;

test('the control falls back to the initials of the authenticated user', function (): void {
    $this->actingAs(User::factory()->createOne([Users::name->value => 'John Doe']));

    $ProfilePicture = ProfilePicture::from([ProfilePicture::field => 'picture']);

    expect($ProfilePicture->name)->toBe('John Doe')
        ->and($ProfilePicture->initials())->toBe('JD')
        ->and($ProfilePicture->picture)->toBeNull();
});

test('the control has no name to read for a guest', function (): void {
    expect(ProfilePicture::from([ProfilePicture::field => 'picture'])->name)->toBeEmpty();
});

test('the control renders the picture the session cached', function (): void {
    $this->actingAs(User::factory()->createOne());
    session([SessionKey::user_picture->value => 'https://example.com/avatar.jpg']);

    expect(ProfilePicture::from([ProfilePicture::field => 'picture'])->picture)
        ->toBe('https://example.com/avatar.jpg');
});

test('an uploaded picture outranks the session', function (): void {
    $User = User::factory()->createOne([Users::picture->value => Directory::profile_pictures->value.'/face.jpg']);
    $this->actingAs($User);
    session([SessionKey::user_picture->value => 'https://example.com/avatar.jpg']);

    expect(Picture::current())->toBe(Disk::public->url(Directory::profile_pictures->value.'/face.jpg'));
});

test('nothing is shown when no source has a picture', function (): void {
    $this->actingAs(User::factory()->createOne());

    expect(Picture::current())->toBeNull()
        ->and(Picture::url(null))->toBeNull();
});
