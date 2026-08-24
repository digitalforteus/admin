<?php

use App\Routes\EnterpriseRoute;
use App\View\DataModels\OrganizationsTable;
use App\View\DataModels\SettingsCard;
use Laravel\Head\Facades\Head;

Head::title('Organizations')
    ->description('The organizations this application manages.')
    ->hiddenFromRobots();
?>
@php
    use App\Models\User;
    use App\Modules\Enterprises\EnterpriseQuery;
    use App\Modules\Settings\Organizations\OrganizationQuery;

    $User = User::authenticated(request());
    $Organizations = OrganizationQuery::get($User);
    $Enterprises = EnterpriseQuery::forUser($User);
@endphp
<x-settings-card :settingsCard="[SettingsCard::title => 'Organizations']">
    <x-status-toast/>
    <div class="mt-6 flex flex-wrap items-center gap-2">
        <a class="btn btn-primary btn-sm" data-enterprise-add href="{{EnterpriseRoute::create->url()}}">New enterprise</a>
        @foreach($Enterprises as $Enterprise)
            <a class="btn btn-sm" data-enterprise-link title="{{$Enterprise->name}}"
               href="{{EnterpriseRoute::index->url([EnterpriseRoute::enterpriseParameter => $Enterprise->slug])}}">{{$Enterprise->name}}</a>
        @endforeach
    </div>
    <x-organizations-table :organizationsTable="[OrganizationsTable::organizations => $Organizations]"/>
</x-settings-card>
