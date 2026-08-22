<?php

use App\Modules\Connections\ConnectionProvider;
use App\Modules\Organizations\OrganizationContext;
use App\View\DataModels\OrganizationCard;
use Laravel\Head\Facades\Head;

Head::title('Connection')
    ->description('A connection this organization has enabled.')
    ->hiddenFromRobots();
?>
@php
    $Organization = OrganizationContext::organization();
    $Connection = OrganizationContext::connection();
    $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => $Connection->name,
]">
    <x-status-toast/>
    {!! $ConnectionPlugin->page($Organization, $Connection)->render() !!}
</x-organization-card>
