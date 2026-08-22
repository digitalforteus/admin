<?php

namespace App\Modules\Organizations\Connections;

use App\Models\Connection;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use App\Sources\Db\App\Connections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class OrganizationConnectionController
{
    public function __invoke(Request $Request, string $organization, string $connection): RedirectResponse
    {
        $Organization = Authorize::manages($Request);

        $Connection = Connection::query()
            ->where(Connections::enterprise_id->value, $Organization->enterprise_id)
            ->where(Connections::slug->value, $connection)
            ->first();

        if (! $Connection instanceof Connection) {
            abort(404);
        }

        if (in_array($Connection->id, ConnectionQuery::enabledIds($Organization), true)) {
            ConnectionQuery::disable($Organization, $Connection);

            return back()->with('status', 'Connection disabled.');
        }

        ConnectionQuery::enable($Organization, $Connection);

        return back()->with('status', 'Connection enabled.');
    }
}
