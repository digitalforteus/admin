<?php

namespace App\Modules\Settings\Profile;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\Picture;
use App\Helpers\Request;
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
            return Picture::rules();
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
