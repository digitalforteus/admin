<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Routes\ContextRoute;
use Zerotoprod\DataModel\Describe;

readonly class ProjectConnectionsTable
{
    use DataModel;

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
        return ContextRoute::connectionCreate->url(ContextRoute::parameters());
    }

    /** @return list<ConnectionRow> */
    public function rows(): array
    {
        return array_map(
            static fn (array $connection): ConnectionRow => ConnectionRow::from($connection),
            $this->connections,
        );
    }
}
