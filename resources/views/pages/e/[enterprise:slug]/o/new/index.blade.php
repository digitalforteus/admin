<?php

use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Routes\ContextRoute;
use App\View\DataModels\ContextCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Organization')
    ->description('An organization inside this enterprise.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\MemberRole;
    use App\Modules\Contexts\Authorize;

    $Enterprise = Authorize::enterprise(MemberRole::admin);
    $parameters = ContextRoute::parameters();
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Enterprise->name, ContextCard::title => 'New Organization']">
    <x-status-toast/>
    <form method="POST" action="{{ContextRoute::organizationIndex->url($parameters)}}" class="mt-6 flex max-w-md flex-col gap-2" data-organization-create>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name),
        ]"/>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-ghost" href="{{ContextRoute::enterprise->url($parameters)}}">Cancel</a>
        </div>
    </form>
</x-context-card>
