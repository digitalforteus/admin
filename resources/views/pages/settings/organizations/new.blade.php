<?php

use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\Auth;
use App\View\DataModels\SettingsCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('New Organization')
    ->description('An organization this account will own.')
    ->hiddenFromRobots();
?>
<x-settings-card :settingsCard="[SettingsCard::title => 'New Organization']">
    <x-status-toast/>
    <form method="POST" action="{{Auth::settingsOrganizations->url()}}" class="mt-6 flex max-w-md flex-col gap-2" data-organization-create>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name),
        ]"/>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="btn btn-primary">Create</button>
            <a class="btn btn-ghost" href="{{Auth::settingsOrganizations->url()}}">Cancel</a>
        </div>
    </form>
</x-settings-card>
