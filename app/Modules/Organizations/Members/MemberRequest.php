<?php

namespace App\Modules\Organizations\Members;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\MemberRole;
use App\Helpers\Request;
use App\Helpers\Rule;

readonly class MemberRequest
{
    use DataModel;
    use IsRequest;

    public const string role = 'role';

    #[Request([
        Request::rules => static function (): array {
            return [Rule::required->value, Rule::in(...MemberRole::values())];
        },
        Request::attributes => 'role',
    ])]
    public string $role;
}
