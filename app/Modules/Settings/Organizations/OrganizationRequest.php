<?php

namespace App\Modules\Settings\Organizations;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\Request;
use App\Sources\Db\App\Organizations;
use Zerotoprod\DataModel\Describe;

readonly class OrganizationRequest
{
    use DataModel;
    use IsRequest;

    public const string name = 'name';

    #[Describe([Describe::cast => [self::class, 'sanitize']])]
    #[Request([Request::rules => static function () {
        return Organizations::name->rules();
    }])]
    public string $name;
}
