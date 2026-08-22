<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class OrganizationCard
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string title = 'title';

    public ?string $title;

    /** @return array<string, mixed> */
    public function pageHeader(): array
    {
        return [PageHeader::title => $this->title];
    }
}
