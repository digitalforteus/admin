<?php

use App\Helpers\OrganizationRole;
use App\Models\Connection;
use App\Modules\Connections\ConnectionQuery;
use App\View\DataModels\ConnectionRow;
use App\View\DataModels\OrganizationCard;
use App\View\DataModels\ProjectConnectionsTable;
use Laravel\Head\Facades\Head;

Head::title('Connections')
    ->description('The connections this project has opted into.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Organizations\MembershipQuery;
    use App\Modules\Organizations\OrganizationContext;

    $Organization = OrganizationContext::organization();
    $Project = OrganizationContext::project();
    $Role = MembershipQuery::role($Organization, request()->user());
    $enabled = ConnectionQuery::enabledIds($Project);
    $connections = array_values(array_map(
        static fn (Connection $Connection): array => [
            ConnectionRow::name => $Connection->name,
            ConnectionRow::slug => $Connection->slug,
            ConnectionRow::provider => $Connection->provider,
            ConnectionRow::enabled => in_array($Connection->id, $enabled, true),
        ],
        ConnectionQuery::ownedBy($Organization)->all(),
    ));
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Project->name,
    OrganizationCard::title => 'Connections',
]">
    <x-status-toast/>
    <x-project-connections-table :projectConnectionsTable="[
        ProjectConnectionsTable::organization => $Organization->slug,
        ProjectConnectionsTable::project => $Project->slug,
        ProjectConnectionsTable::manages => $Role?->manages() ?? false,
        ProjectConnectionsTable::owns => $Role === OrganizationRole::owner,
        ProjectConnectionsTable::connections => $connections,
    ]"/>
</x-organization-card>
