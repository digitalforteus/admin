<?php

use App\Helpers\SvgName;
use App\Routes\ContextRoute;
use App\View\DataModels\Avatar;
use App\View\DataModels\ContextCard;
use Laravel\Head\Facades\Head;

Head::title('Enterprises')
    ->description('The enterprises this account belongs to.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\Depth;
    use App\Models\User;
    use App\Modules\Contexts\DepthQuery;

    $Enterprises = DepthQuery::children(Depth::enterprise, null, User::authenticated(request()));
@endphp
<x-context-card :contextCard="[ContextCard::heading => 'Enterprises', ContextCard::title => 'Where this account works']">
    <x-status-toast/>
    <div class="mt-6">
        <a class="btn btn-primary btn-sm" data-enterprise-add href="{{ContextRoute::enterpriseCreate->url()}}">New enterprise</a>
    </div>
    <ul class="mt-6 grid grid-cols-1 gap-2 lg:grid-cols-2">
        @forelse($Enterprises as $Enterprise)
            <li data-enterprise-row>
                <a class="flex items-center gap-3 rounded-box border border-base-300 p-3"
                   href="{{ContextRoute::enterprise->url([ContextRoute::enterpriseParameter => $Enterprise->slug])}}">
                    <x-avatar :avatar="[Avatar::name => $Enterprise->name, Avatar::size => 'w-8', Avatar::fallback => SvgName::city]"/>
                    <span class="truncate font-medium" title="{{$Enterprise->name}}">{{$Enterprise->name}}</span>
                </a>
            </li>
        @empty
            <li data-enterprises-empty class="text-base-content/70">No enterprises yet.</li>
        @endforelse
    </ul>
</x-context-card>
