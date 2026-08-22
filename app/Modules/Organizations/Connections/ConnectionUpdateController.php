<?php

namespace App\Modules\Organizations\Connections;

use App\Modules\Connections\ConnectionPlugin;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\Authorize;
use App\Sources\Db\App\Connections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ConnectionUpdateController
{
    public function __invoke(Request $Request, string $organization, string $connection): RedirectResponse
    {
        $Connection = ConnectionQuery::find(Authorize::owns($Request), $connection);
        $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);

        $ConnectionRequest = ConnectionRequest::from($Request->all());
        $errors = Validator::make(...$ConnectionRequest->validator())->errors();
        $credentials = $Connection->credentials;
        $config = $Connection->config ?? [];

        if ($ConnectionPlugin instanceof ConnectionPlugin) {
            $fields = ConnectionFields::merge($ConnectionPlugin, $Connection, $Request->all());
            $credentials = $fields[Connections::credentials->value];
            $config = $fields[Connections::config->value];
            $errors = $errors->merge($ConnectionPlugin->validate(ConnectionFields::values($fields))->errors());
        }

        if ($errors->isNotEmpty()) {
            return back()
                ->withErrors($errors)
                ->withInput([...$ConnectionRequest->toArray(), ...$config]);
        }

        $Connection->update([
            Connections::name->value => $ConnectionRequest->name,
            Connections::credentials->value => $credentials,
            Connections::config->value => $config,
        ]);

        return back()->with('status', 'Connection updated.');
    }
}
