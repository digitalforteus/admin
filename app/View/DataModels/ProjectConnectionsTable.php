<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

readonly class ProjectConnectionsTable
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string project = 'project';

    #[Describe([Describe::required => true])]
    public string $project;

    public const string manages = 'manages';

    #[Describe([Describe::default => false])]
    public bool $manages;

    public const string owns = 'owns';

    #[Describe([Describe::default => false])]
    public bool $owns;

    public const string connections = 'connections';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $connections;

    public function createUrl(): string
    {
        return OrganizationRoute::connectionCreate->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::projectParameter => $this->project,
        ]);
    }

    /** @return list<ConnectionRow> */
    public function rows(): array
    {
        return array_map(
            fn (array $connection): ConnectionRow => ConnectionRow::from([
                ConnectionRow::organization => $this->organization,
                ConnectionRow::project => $this->project,
                ...$connection,
            ]),
            $this->connections,
        );
    }
}
