<?php

use App\Modules\Connections\ConnectionProvider;
use App\Modules\Organizations\Authorize;
use App\Modules\Organizations\Connections\ConnectionFields;
use App\Modules\Organizations\Connections\ConnectionForm;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Connections;
use App\View\DataModels\OrganizationCard;
use App\View\DataModels\Svg;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Connection')
    ->description('The credentials an enterprise holds for a provider.')
    ->hiddenFromRobots();
?>
@php
    $Organization = Authorize::owns(request());
    $Provider = ConnectionProvider::tryFromKey(request()->string(Connections::provider->value)->value());
    $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => 'New Connection',
]">
    <x-status-toast/>
    @if($Provider === null)
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach(ConnectionProvider::cases() as $Case)
                @php($ConnectionPlugin = $Case->plugin())
                <a class="rounded-box border border-base-300 p-4 hover:border-primary"
                   data-connection-provider
                   title="{{$ConnectionPlugin->label()}}"
                   href="{{OrganizationRoute::connectionCreate->url($parameters, [Connections::provider->value => $Case->name])}}">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <x-svg :svg="[Svg::name => $ConnectionPlugin->icon(), Svg::classname => 'h-5 w-5 opacity-70']"/>
                        {{$ConnectionPlugin->label()}}
                    </span>
                </a>
            @endforeach
        </div>
    @else
        @php($inputs = ConnectionFields::inputs($Provider->plugin()))
        <form method="POST" action="{{OrganizationRoute::connections->url($parameters)}}" class="mt-6 flex max-w-md flex-col gap-2">
            @csrf
            <input type="hidden" name="{{Connections::provider->value}}" value="{{$Provider->name}}"/>
            <x-text-input :textInput="[
                ...ConnectionForm::textInput(ConnectionForm::name),
                TextInput::value => old(ConnectionForm::name),
            ]"/>
            @foreach($inputs as $input)
                <x-text-input :textInput="$input"/>
            @endforeach
            <div class="mt-2 flex gap-2">
                <button type="submit" class="btn btn-primary" data-connection-create>Create</button>
                <a class="btn btn-ghost" href="{{OrganizationRoute::connections->url($parameters)}}">Cancel</a>
            </div>
        </form>
    @endif
</x-organization-card>
