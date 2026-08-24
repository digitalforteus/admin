<?php

use App\Modules\Projects\ProjectForm;
use App\Routes\ContextRoute;
use App\View\DataModels\ContextCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Project')
    ->description('A project inside this organization.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\MemberRole;
    use App\Modules\Contexts\Authorize;

    $Organization = Authorize::organization(MemberRole::admin);
    $parameters = ContextRoute::parameters();
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Organization->name, ContextCard::title => 'New Project']">
    <x-status-toast/>
    <form method="POST" action="{{ContextRoute::projectIndex->url($parameters)}}" class="mt-6 flex max-w-md flex-col gap-2" data-project-create>
        @csrf
        <x-text-input :textInput="[
            ...ProjectForm::textInput(ProjectForm::name),
            TextInput::value => old(ProjectForm::name),
        ]"/>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-ghost" href="{{ContextRoute::organization->url($parameters)}}">Cancel</a>
        </div>
    </form>
</x-context-card>
