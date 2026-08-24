<?php

use App\Modules\Enterprises\EnterpriseForm;
use App\Routes\Auth;
use App\Routes\EnterpriseRoute;
use App\View\DataModels\ContextCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Enterprise')
    ->description('An enterprise this account will own.')
    ->hiddenFromRobots();
?>
<x-context-card :contextCard="[ContextCard::heading => 'New Enterprise', ContextCard::title => 'Name it and its first organization']">
    <x-status-toast/>
    <form method="POST" action="{{EnterpriseRoute::create->url()}}" class="mt-6 flex max-w-md flex-col gap-2" data-enterprise-create>
        @csrf
        <x-text-input :textInput="[
            ...EnterpriseForm::textInput(EnterpriseForm::name),
            TextInput::value => old(EnterpriseForm::name),
        ]"/>
        <x-text-input :textInput="[
            ...EnterpriseForm::textInput(EnterpriseForm::organization),
            TextInput::value => old(EnterpriseForm::organization),
        ]"/>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-ghost" href="{{Auth::settingsOrganizations->url()}}">Cancel</a>
        </div>
    </form>
</x-context-card>
