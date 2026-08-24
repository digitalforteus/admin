<?php

use App\Modules\Connections\ConnectionProvider;
use App\View\DataModels\OrganizationCard;
use Laravel\Head\Facades\Head;

Head::title('Connection')
    ->description('A connection this project has enabled.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Organizations\OrganizationContext;

    $Project = OrganizationContext::project();
    $Connection = OrganizationContext::connection();
    $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Project->name,
    OrganizationCard::title => $Connection->name,
]">
    <x-status-toast/>
    {!! $ConnectionPlugin->page($Project, $Connection)->render() !!}
</x-organization-card>
