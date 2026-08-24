<?php

use App\Modules\Enterprises\EnterpriseForm;
use App\Routes\EnterpriseRoute;
use App\View\DataModels\ContextCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('Enterprise Settings')
    ->description('Update this enterprise\'s name.')
    ->hiddenFromRobots();
?>
@php
    use App\Models\User;
    use App\Modules\Enterprises\EnterpriseContext;
    use App\Modules\Enterprises\EnterpriseQuery;

    $User = User::authenticated(request());
    $Enterprise = EnterpriseQuery::owned($User, EnterpriseContext::enterprise()->slug);
    $Organizations = EnterpriseQuery::organizations($Enterprise, $User);
    $parameters = [EnterpriseRoute::enterpriseParameter => $Enterprise->slug];
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Enterprise->name, ContextCard::title => 'Enterprise Settings']">
    <x-status-toast/>
    <form class="mt-6 max-w-md space-y-4" method="POST" action="{{EnterpriseRoute::settings->url($parameters)}}" data-enterprise-form>
        @csrf
        <x-text-input :textInput="[
            ...EnterpriseForm::textInput(EnterpriseForm::name),
            TextInput::value => old(EnterpriseForm::name, $Enterprise->name),
        ]"/>
        <button class="btn btn-primary">Save</button>
    </form>
    <div class="mt-6 rounded-box border border-base-300 p-4">
        <h2 class="text-xs uppercase tracking-wider text-base-content/55">Organizations it holds</h2>
        <ul class="mt-2 space-y-1">
            @foreach($Organizations as $Organization)
                <li data-enterprise-organization title="{{$Organization->name}}">{{$Organization->name}}</li>
            @endforeach
        </ul>
    </div>
</x-context-card>
