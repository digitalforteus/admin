<?php

use App\Models\Connection;
use App\Modules\Connections\ConnectionQuery;
use App\Helpers\OrganizationRole;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\View\DataModels\ConnectionRow;
use App\View\DataModels\OrganizationCard;
use App\View\DataModels\OrganizationConnectionsTable;
use Laravel\Head\Facades\Head;

Head::title('Connections')
    ->description('The connections this organization has opted into.')
    ->hiddenFromRobots();
?>
@php
    $Organization = OrganizationContext::organization();
    $Role = MembershipQuery::role($Organization, request()->user());
    $enabled = ConnectionQuery::enabledIds($Organization);
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
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => 'Connections',
]">
    <x-status-toast/>
    <x-organization-connections-table :organizationConnectionsTable="[
        OrganizationConnectionsTable::organization => $Organization->slug,
        OrganizationConnectionsTable::manages => $Role?->manages() ?? false,
        OrganizationConnectionsTable::owns => $Role === OrganizationRole::owner,
        OrganizationConnectionsTable::connections => $connections,
    ]"/>
</x-organization-card>
