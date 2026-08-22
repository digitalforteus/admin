<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\OrganizationRole;
use App\Routes\OrganizationRoute;
use Illuminate\Support\Carbon;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class InvitationRow
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string id = 'id';

    #[Describe([Describe::required => true])]
    public string $id;

    public const string email = 'email';

    #[Describe([Describe::required => true])]
    public string $email;

    public const string role = 'role';

    #[Describe([Describe::required => true])]
    public OrganizationRole $role;

    public const string expires_at = 'expires_at';

    public ?string $expires_at;

    public function expires(): string
    {
        return $this->expires_at !== null ? Carbon::parse($this->expires_at)->diffForHumans() : '—';
    }

    public function url(): string
    {
        return OrganizationRoute::invitation->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::invitationParameter => $this->id,
        ]);
    }
}
