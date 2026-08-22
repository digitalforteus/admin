<?php

use App\Helpers\Disk;
use App\Helpers\Extension;
use App\Routes\Auth;
use App\View\DataModels\PictureField;

/** @param  array<string, mixed>  $overrides */
function pictureField(array $overrides = []): PictureField
{
    return PictureField::from([
        PictureField::field => 'picture',
        PictureField::action => Auth::settingsProfilePicture->value,
        ...$overrides,
    ]);
}

test('the field falls back to the initials of what it stands for', function (): void {
    expect(pictureField([PictureField::label => 'John Doe'])->initials())->toBe('JD');
});

test('the field accepts the extensions an image may be sent as', function (): void {
    expect(pictureField()->accept)->toBe(Extension::imageFilter())
        ->and(pictureField()->uploads)->toBe(Disk::retains());
});

test('the form that removes a picture is named after the field it belongs to', function (): void {
    expect(pictureField()->remove())->toBe('picture-remove');
});

test('a field carries no picture until one is given', function (): void {
    expect(pictureField()->picture)->toBeNull()
        ->and(pictureField([PictureField::picture => '/storage/a.png'])->picture)->toBe('/storage/a.png');
});
