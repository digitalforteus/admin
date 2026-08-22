<?php

namespace App\Modules\Connections\Github;

use App\Helpers\DataModel;

readonly class GithubRuns
{
    use DataModel;

    public const string ok = 'ok';

    public bool $ok;

    public const string status = 'status';

    public int $status;

    public const string total = 'total';

    public int $total;

    public const string rows = 'rows';

    /** @var list<array<string, mixed>> */
    public array $rows;
}
