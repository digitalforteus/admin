<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

readonly class RunsTable
{
    use DataModel;

    public const int perPage = 20;
    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string connection = 'connection';

    #[Describe([Describe::required => true])]
    public string $connection;

    public const string ok = 'ok';

    #[Describe([Describe::default => true])]
    public bool $ok;

    public const string status = 'status';

    #[Describe([Describe::default => 0])]
    public int $status;

    public const string total = 'total';

    #[Describe([Describe::default => 0])]
    public int $total;

    public const string page = 'page';

    #[Describe([Describe::default => 1])]
    public int $page;

    public const string runs = 'runs';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $runs;

    /** @return list<RunRow> */
    public function rows(): array
    {
        return array_map(static fn (array $run): RunRow => RunRow::from($run), $this->runs);
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function span(): int
    {
        return 8;
    }

    public function summary(): string
    {
        return $this->total === 0 ? 'No runs' : 'Showing '.count($this->runs).' of '.$this->total;
    }

    public function previousUrl(): ?string
    {
        return $this->page > 1 ? $this->pageUrl($this->page - 1) : null;
    }

    public function nextUrl(): ?string
    {
        return $this->page * self::perPage < $this->total ? $this->pageUrl($this->page + 1) : null;
    }

    private function pageUrl(int $page): string
    {
        return OrganizationRoute::connection->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::connectionParameter => $this->connection,
        ], [self::page => $page]);
    }
}
