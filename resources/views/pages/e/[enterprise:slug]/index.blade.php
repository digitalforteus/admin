<?php

use App\Helpers\SvgName;
use App\Routes\ContextRoute;
use App\View\DataModels\Avatar;
use App\View\DataModels\ContextCard;
use Laravel\Head\Facades\Head;

Head::title('Enterprise')
    ->description('The organizations this enterprise holds.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\Depth;
    use App\Helpers\MemberRole;
    use App\Models\User;
    use App\Modules\Contexts\Context;
    use App\Modules\Contexts\DepthQuery;

    $Enterprise = Context::enterprise();
    $Organizations = DepthQuery::children(Depth::organization, $Enterprise, User::authenticated(request()));
    $Role = Context::role(Depth::enterprise);
    $parameters = ContextRoute::parameters();
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Enterprise->name, ContextCard::title => 'Organizations']">
    <x-status-toast/>
    <div class="mt-6 flex flex-wrap gap-2">
        @if($Role?->manages())
            <a class="btn btn-primary btn-sm" data-organization-add
               href="{{ContextRoute::organizationCreate->url($parameters)}}">Add organization</a>
        @endif
        @if($Role === MemberRole::owner)
            <a class="btn btn-sm" data-enterprise-settings
               href="{{ContextRoute::enterpriseSettings->url($parameters)}}">Enterprise settings</a>
        @endif
    </div>
    <ul class="mt-6 grid grid-cols-1 gap-2 lg:grid-cols-2">
        @forelse($Organizations as $Organization)
            <li data-organization-row>
                <a class="flex items-center gap-3 rounded-box border border-base-300 p-3"
                   href="{{ContextRoute::organization->url([...$parameters, ContextRoute::organizationParameter => $Organization->slug])}}">
                    <x-avatar :avatar="[
                        Avatar::name => $Organization->name,
                        Avatar::picture => $Organization->iconUrl(),
                        Avatar::size => 'w-8',
                        Avatar::fallback => SvgName::building,
                    ]"/>
                    <span class="truncate font-medium" title="{{$Organization->name}}">{{$Organization->name}}</span>
                </a>
            </li>
        @empty
            <li data-organizations-empty class="text-base-content/70">No organizations yet.</li>
        @endforelse
    </ul>
</x-context-card>
