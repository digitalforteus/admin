<?php

use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\EnterpriseRoute;
use App\View\DataModels\ContextCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Organization')
    ->description('An organization inside this enterprise.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Enterprises\EnterpriseContext;

    $Enterprise = EnterpriseContext::enterprise();
    $parameters = [EnterpriseRoute::enterpriseParameter => $Enterprise->slug];
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Enterprise->name, ContextCard::title => 'New Organization']">
    <x-status-toast/>
    <form method="POST" action="{{EnterpriseRoute::organizationCreate->url($parameters)}}" class="mt-6 flex max-w-md flex-col gap-2" data-organization-create>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name),
        ]"/>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-ghost" href="{{EnterpriseRoute::index->url($parameters)}}">Cancel</a>
        </div>
    </form>
</x-context-card>
