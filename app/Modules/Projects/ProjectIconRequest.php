<?php

namespace App\Modules\Projects;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\Picture;
use App\Helpers\Request;
use Illuminate\Http\UploadedFile;
use Zerotoprod\DataModel\Describe;

readonly class ProjectIconRequest
{
    use DataModel;
    use IsRequest;

    public const string icon = 'icon';

    #[Describe([Describe::nullable => true])]
    #[Request([
        Request::rules => static function () {
            return Picture::rules();
        },
        Request::attributes => 'project icon',
    ])]
    public ?UploadedFile $icon;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [self::icon => $this->icon];
    }
}
