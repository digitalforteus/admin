<?php

use App\Modules\Projects\ProjectForm;
use App\Routes\OrganizationRoute;
use App\View\DataModels\OrganizationCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Project')
    ->description('A project inside this organization.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Organizations\Authorize;

    $Organization = Authorize::manages(request());
    $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];
@endphp
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => 'New Project',
]">
    <x-status-toast/>
    <form method="POST" action="{{OrganizationRoute::projects->url($parameters)}}" class="mt-6 flex max-w-md flex-col gap-2" data-project-create>
        @csrf
        <x-text-input :textInput="[
            ...ProjectForm::textInput(ProjectForm::name),
            TextInput::value => old(ProjectForm::name),
        ]"/>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-ghost" href="{{OrganizationRoute::projects->url($parameters)}}">Cancel</a>
        </div>
    </form>
</x-organization-card>
