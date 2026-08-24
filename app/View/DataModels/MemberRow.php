<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\MemberRole;
use App\Routes\ContextRoute;
use Zerotoprod\DataModel\Describe;

readonly class MemberRow
{
    use DataModel;

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
    public MemberRole $role;

    public function url(): string
    {
        return ContextRoute::member->url(ContextRoute::parameters([ContextRoute::memberParameter => $this->id]));
    }
}
