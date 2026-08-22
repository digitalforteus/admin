<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\OrganizationRole;
use App\Modules\Organizations\Invitations\InvitationForm;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

readonly class OrganizationMembersTable
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string manages = 'manages';

    #[Describe([Describe::default => false])]
    public bool $manages;

    public const string owns = 'owns';

    #[Describe([Describe::default => false])]
    public bool $owns;

    public const string members = 'members';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $members;

    public const string invitations = 'invitations';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $invitations;

    /** @return list<MemberRow> */
    public function rows(): array
    {
        return array_map(
            fn (array $member): MemberRow => MemberRow::from([
                MemberRow::organization => $this->organization,
                ...$member,
            ]),
            $this->members,
        );
    }

    /** @return list<InvitationRow> */
    public function pending(): array
    {
        return array_map(
            fn (array $invitation): InvitationRow => InvitationRow::from([
                InvitationRow::organization => $this->organization,
                ...$invitation,
            ]),
            $this->invitations,
        );
    }

    /** @return list<OrganizationRole> */
    public function roles(): array
    {
        return OrganizationRole::cases();
    }

    public function action(): string
    {
        return OrganizationRoute::invitations->url([
            OrganizationRoute::organizationParameter => $this->organization,
        ]);
    }

    /** @return array<string, mixed> */
    public function emailInput(): array
    {
        return InvitationForm::textInput(InvitationForm::email);
    }
}
