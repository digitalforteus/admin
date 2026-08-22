<?php

use App\Models\User;
use App\Modules\Settings\Organizations\OrganizationQuery;
use App\View\DataModels\SettingsCard;
use App\View\DataModels\OrganizationsTable;
use Laravel\Head\Facades\Head;

Head::title('Organizations')
    ->description('The organizations this application manages.')
    ->hiddenFromRobots();
?>
@php
    $Organizations = OrganizationQuery::get(User::authenticated(request()));
@endphp
<x-settings-card :settingsCard="[SettingsCard::title => 'Organizations']">
    <x-status-toast/>
    <x-organizations-table :organizationsTable="[OrganizationsTable::organizations => $Organizations]"/>
</x-settings-card>
