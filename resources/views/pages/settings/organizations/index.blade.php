<?php

use App\Modules\Settings\Organizations\OrganizationQuery;
use App\View\DataModels\SettingsCard;
use App\View\DataModels\OrganizationsTable;
use Laravel\Head\Facades\Head;

Head::title('Organizations')
    ->description('The organizations this application manages.')
    ->hiddenFromRobots();
?>
@php
    $organizations = OrganizationQuery::get();
@endphp
<x-settings-card :settingsCard="[SettingsCard::title => 'Organizations']">
    <x-status-toast/>
    <x-organizations-table :organizationsTable="[OrganizationsTable::organizations => $organizations]"/>
</x-settings-card>
