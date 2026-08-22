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

test('the field falls back to initials, accepts what an image may be sent as, and names its own remove form', function (): void {
    expect(pictureField([PictureField::label => 'John Doe'])->initials())->toBe('JD')
        ->and(pictureField()->accept)->toBe(Extension::imageFilter())
        ->and(pictureField()->uploads)->toBe(Disk::retains())
        ->and(pictureField()->remove())->toBe('picture-remove')
        ->and(pictureField()->picture)->toBeNull()
        ->and(pictureField([PictureField::picture => '/storage/a.png'])->picture)->toBe('/storage/a.png');
});
