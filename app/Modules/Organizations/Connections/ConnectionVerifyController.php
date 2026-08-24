<?php

namespace App\Modules\Organizations\Connections;

use App\Modules\Connections\ConnectionPlugin;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionVerifyController
{
    public function __invoke(Request $Request, string $organization, string $project, string $connection): RedirectResponse
    {
        $Connection = ConnectionQuery::find(Authorize::owns($Request), $connection);
        $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);

        if (! $ConnectionPlugin instanceof ConnectionPlugin) {
            abort(404);
        }

        return back()->with('status', $ConnectionPlugin->verify($Connection)
            ? 'Connection verified.'
            : 'Connection could not be verified.');
    }
}
