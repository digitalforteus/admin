<?php

namespace App\Modules\Projects;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\Request;
use App\Sources\Db\App\Projects;
use Zerotoprod\DataModel\Describe;

readonly class ProjectRequest
{
    use DataModel;
    use IsRequest;

    public const string name = 'name';

    #[Describe([Describe::cast => [self::class, 'sanitize']])]
    #[Request([Request::rules => static function () {
        return Projects::name->rules();
    }])]
    public string $name;
}
