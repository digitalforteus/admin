<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\Auth;
use Zerotoprod\DataModel\Describe;

readonly class OrganizationsTable
{
    use DataModel;

    public const string organizations = 'organizations';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::required => true])]
    public array $organizations;

    public function action(): string
    {
        return Auth::settingsOrganizations->url();
    }

    /** @return array<string, mixed> */
    public function nameInput(): array
    {
        return OrganizationForm::textInput(OrganizationForm::name);
    }

    /** @return list<OrganizationRow> */
    public function rows(): array
    {
        return array_map(static fn (array $organization): OrganizationRow => OrganizationRow::from($organization), $this->organizations);
    }
}
