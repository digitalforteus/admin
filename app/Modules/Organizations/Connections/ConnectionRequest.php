<?php

namespace App\Modules\Organizations\Connections;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\Request;
use App\Sources\Db\App\Connections;
use Zerotoprod\DataModel\Describe;

readonly class ConnectionRequest
{
    use DataModel;
    use IsRequest;

    public const string name = 'name';

    #[Describe([Describe::cast => [self::class, 'sanitize']])]
    #[Request([Request::rules => static function (): array {
        return Connections::name->rules();
    }])]
    public string $name;
}
