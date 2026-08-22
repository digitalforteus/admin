<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Initials;
use App\Helpers\OrganizationRole;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

readonly class MemberRow
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string id = 'id';

    #[Describe([Describe::required => true])]
    public string $id;

    public const string name = 'name';

    #[Describe([Describe::required => true])]
    public string $name;

    public const string email = 'email';

    #[Describe([Describe::required => true])]
    public string $email;

    public const string role = 'role';

    #[Describe([Describe::required => true])]
    public OrganizationRole $role;

    public function initials(): string
    {
        return Initials::from($this->name);
    }

    public function url(): string
    {
        return OrganizationRoute::member->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::memberParameter => $this->id,
        ]);
    }
}
