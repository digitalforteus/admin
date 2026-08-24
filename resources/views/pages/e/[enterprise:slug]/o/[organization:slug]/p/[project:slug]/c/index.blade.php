<?php

use App\Models\Connection;
use App\View\DataModels\ConnectionRow;
use App\View\DataModels\ContextCard;
use App\View\DataModels\ProjectConnectionsTable;
use Laravel\Head\Facades\Head;

Head::title('Connections')
    ->description('The connections this project has opted into.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\Depth;
    use App\Helpers\MemberRole;
    use App\Modules\Connections\ConnectionQuery;
    use App\Modules\Contexts\Context;

    $Project = Context::project();
    $Role = Context::role(Depth::project);
    $enabled = ConnectionQuery::enabledIds($Project);
    $connections = array_values(array_map(
        static fn (Connection $Connection): array => [
            ConnectionRow::name => $Connection->name,
            ConnectionRow::slug => $Connection->slug,
            ConnectionRow::provider => $Connection->provider,
            ConnectionRow::enabled => in_array($Connection->id, $enabled, true),
        ],
        ConnectionQuery::ownedBy($Project->organization)->all(),
    ));
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Project->name, ContextCard::title => 'Connections']">
    <x-status-toast/>
    <x-project-connections-table :projectConnectionsTable="[
        ProjectConnectionsTable::manages => $Role?->manages() ?? false,
        ProjectConnectionsTable::owns => $Role === MemberRole::owner,
        ProjectConnectionsTable::connections => $connections,
    ]"/>
</x-context-card>
