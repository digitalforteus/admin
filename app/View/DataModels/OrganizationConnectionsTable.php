<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

readonly class OrganizationConnectionsTable
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string manages = 'manages';

    #[Describe([Describe::default => false])]
    public bool $manages;

    public const string connections = 'connections';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $connections;

    /** @return list<ConnectionRow> */
    public function rows(): array
    {
        return array_map(
            fn (array $connection): ConnectionRow => ConnectionRow::from([
                ConnectionRow::organization => $this->organization,
                ...$connection,
            ]),
            $this->connections,
        );
    }
}
