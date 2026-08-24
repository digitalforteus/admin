<?php

namespace App\Modules\Organizations\Connections;

use App\Helpers\MemberRole;
use App\Helpers\Slug;
use App\Models\Connection;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Contexts\Authorize;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Connections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ConnectionStoreController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Project = Authorize::project(MemberRole::owner);
        $Organization = $Project->organization;
        $parameters = ContextRoute::parameters();
        $Provider = ConnectionProvider::tryFromKey($Request->string(Connections::provider->value)->value());

        if (! $Provider instanceof ConnectionProvider) {
            return redirect()->to(ContextRoute::connectionCreate->url($parameters));
        }

        $ConnectionPlugin = $Provider->plugin();
        $ConnectionRequest = ConnectionRequest::from($Request->all());
        $fields = ConnectionFields::split($ConnectionPlugin, $Request->all());
        $errors = Validator::make(...$ConnectionRequest->validator())->errors()
            ->merge($ConnectionPlugin->validate(ConnectionFields::values($fields))->errors());

        if ($errors->isNotEmpty()) {
            return back()
                ->withErrors($errors)
                ->withInput([...$ConnectionRequest->toArray(), ...$fields[Connections::config->value]]);
        }

        $Connection = Connection::query()->create([
            Connections::enterprise_id->value => $Organization->enterprise_id,
            Connections::provider->value => $Provider->name,
            Connections::name->value => $ConnectionRequest->name,
            Connections::slug->value => Slug::unique(
                Connection::class,
                Connections::slug->value,
                $ConnectionRequest->name,
                [Connections::enterprise_id->value => $Organization->enterprise_id],
            ),
            Connections::credentials->value => $fields[Connections::credentials->value],
            Connections::config->value => $fields[Connections::config->value],
        ]);

        ConnectionQuery::enable($Project, $Connection);

        return redirect()
            ->to(ContextRoute::connectionSettings->url([
                ...$parameters,
                ContextRoute::connectionParameter => $Connection->slug,
            ]))
            ->with('status', 'Connection created.');
    }
}
