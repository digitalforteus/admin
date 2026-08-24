<?php

use App\Modules\Connections\ConnectionProvider;
use App\Modules\Organizations\Connections\ConnectionFields;
use App\Modules\Organizations\Connections\ConnectionForm;
use App\Routes\ContextRoute;
use App\View\DataModels\ContextCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('Connection')
    ->description('The credentials an enterprise holds for a provider.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\MemberRole;
    use App\Modules\Connections\ConnectionQuery;
    use App\Modules\Contexts\Authorize;

    $Organization = Authorize::organization(MemberRole::owner);
    $Project = Authorize::project(MemberRole::owner);
    $Connection = ConnectionQuery::find($Organization, $connection);
    $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);
    $Projects = ConnectionQuery::projects($Connection);
    $parameters = ContextRoute::parameters([ContextRoute::connectionParameter => $Connection->slug]);
    $settings = ContextRoute::connectionSettings->url($parameters);
    $inputs = $ConnectionPlugin === null ? [] : ConnectionFields::inputs($ConnectionPlugin, $Connection);
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Project->name, ContextCard::title => $Connection->name]">
    <x-status-toast/>
    @if($ConnectionPlugin === null)
        <p class="mt-6">
            <span class="badge badge-ghost" data-connection-unavailable title="Unavailable">Unavailable</span>
        </p>
    @endif
    <form method="POST" action="{{$settings}}" class="mt-6 flex max-w-md flex-col gap-2">
        @csrf
        <x-text-input :textInput="[
            ...ConnectionForm::textInput(ConnectionForm::name),
            TextInput::value => old(ConnectionForm::name, $Connection->name),
        ]"/>
        @foreach($inputs as $input)
            <x-text-input :textInput="$input"/>
        @endforeach
        <div class="mt-2">
            <button type="submit" class="btn btn-primary" data-connection-save>Save</button>
        </div>
    </form>
    <div class="mt-4 flex flex-wrap gap-2">
        @if($ConnectionPlugin !== null)
            <form method="POST" action="{{ContextRoute::connectionVerify->url($parameters)}}">
                @csrf
                <button type="submit" class="btn btn-sm" data-connection-verify>Verify</button>
            </form>
        @endif
        <form method="POST" action="{{$settings}}"
              onsubmit="return confirm('Delete this connection? Every project loses it.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-error" data-connection-delete>Delete</button>
        </form>
    </div>
    <div class="mt-6 overflow-x-auto rounded-box border border-base-300">
        <table class="table table-zebra">
            <thead>
            <tr>
                <th>Enabled for</th>
            </tr>
            </thead>
            <tbody>
            @forelse($Projects as $Enabled)
                <tr data-connection-project>
                    <td class="whitespace-nowrap" title="{{$Enabled->name}}">{{$Enabled->name}}</td>
                </tr>
            @empty
                <tr data-connection-projects-empty>
                    <td class="text-center text-base-content/70">No projects have this enabled.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-context-card>
