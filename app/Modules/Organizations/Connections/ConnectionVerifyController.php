<?php

namespace App\Modules\Organizations\Connections;

use App\Helpers\MemberRole;
use App\Modules\Connections\ConnectionPlugin;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Contexts\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ConnectionVerifyController
{
    public function __invoke(Request $Request, string $enterprise, string $organization, string $project, string $connection): RedirectResponse
    {
        $Connection = ConnectionQuery::find(Authorize::organization(MemberRole::owner), $connection);
        $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);

        if (! $ConnectionPlugin instanceof ConnectionPlugin) {
            abort(404);
        }

        return back()->with('status', $ConnectionPlugin->verify($Connection)
            ? 'Connection verified.'
            : 'Connection could not be verified.');
    }
}
