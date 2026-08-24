<?php

namespace App\Modules\Enterprises;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\Request;
use App\Sources\Db\App\Enterprises;
use Zerotoprod\DataModel\Describe;

readonly class EnterpriseRequest
{
    use DataModel;
    use IsRequest;

    public const string name = 'name';

    #[Describe([Describe::cast => [self::class, 'sanitize']])]
    #[Request([Request::rules => static function () {
        return Enterprises::name->rules();
    }])]
    public string $name;
}
