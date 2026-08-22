<?php

namespace App\Modules\Settings\Profile;

use App\Helpers\DataModel;
use App\Helpers\Extension;
use App\Helpers\IsRequest;
use App\Helpers\ProfilePicture;
use App\Helpers\Request;
use App\Helpers\Rule;
use Illuminate\Http\UploadedFile;
use Zerotoprod\DataModel\Describe;

readonly class ProfilePictureRequest
{
    use DataModel;
    use IsRequest;

    public const string picture = 'picture';

    #[Describe([Describe::nullable => true])]
    #[Request([
        Request::rules => static function () {
            return [
                Rule::required,
                Rule::image,
                Rule::mimes(...Extension::imageValues()),
                Rule::max(ProfilePicture::kilobytes),
            ];
        },
        Request::attributes => 'profile picture',
    ])]
    public ?UploadedFile $picture;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [self::picture => $this->picture];
    }
}
