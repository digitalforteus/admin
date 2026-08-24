<?php

namespace App\Modules\Organizations\Invitations;

use App\Helpers\DataModel;
use App\Helpers\IsRequest;
use App\Helpers\MemberRole;
use App\Helpers\Request;
use App\Helpers\Rule;
use App\Sources\Db\App\OrganizationInvitations;
use Zerotoprod\DataModel\Describe;

readonly class InvitationRequest
{
    use DataModel;
    use IsRequest;

    public const string email = 'email';

    #[Describe([Describe::cast => [self::class, 'sanitizeEmail']])]
    #[Request([Request::rules => static function (): array {
        return [...OrganizationInvitations::email->rules(), Rule::email->value];
    }])]
    public string $email;

    public const string role = 'role';

    #[Request([Request::rules => static function (): array {
        return [Rule::required->value, Rule::in(...MemberRole::values())];
    }])]
    public string $role;
}
