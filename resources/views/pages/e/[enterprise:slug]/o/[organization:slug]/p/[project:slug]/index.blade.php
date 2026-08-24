<?php

use App\Routes\ContextRoute;
use App\View\DataModels\ContextCard;
use Laravel\Head\Facades\Head;

Head::title('Project')
    ->description('The project this account is working in.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\Depth;
    use App\Helpers\MemberRole;
    use App\Modules\Contexts\Context;

    $Organization = Context::organization();
    $Project = Context::project();
    $Role = Context::role(Depth::project);
    $parameters = ContextRoute::parameters();
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Project->name, ContextCard::title => 'Overview']">
    <x-status-toast/>
    <dl class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Organization</dt>
            <dd class="mt-1 font-medium" title="{{$Organization->name}}">{{$Organization->name}}</dd>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Your standing</dt>
            <dd class="mt-1 font-medium" title="{{$Role?->value}}">{{$Role?->label() ?? '—'}}</dd>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <dt class="text-xs uppercase tracking-wider text-base-content/55">Created by</dt>
            <dd class="mt-1 font-medium" title="{{$Project->creator?->name}}">{{$Project->creator?->name ?? '—'}}</dd>
        </div>
    </dl>
    <div class="mt-6 flex flex-wrap gap-2">
        <a class="btn btn-sm" href="{{ContextRoute::connectionIndex->url($parameters)}}">Connections</a>
        @if($Role?->manages())
            <a class="btn btn-sm" data-project-settings
               href="{{ContextRoute::projectSettings->url($parameters)}}">Project settings</a>
        @endif
    </div>
</x-context-card>
