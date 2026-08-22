<?php

use App\Modules\Settings\Organizations\OrganizationForm;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Sources\Db\App\Organizations;
use App\View\DataModels\PictureField;
use App\View\DataModels\SettingsCard;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('Organization')
    ->description('Update this organization\'s name and icon.')
    ->hiddenFromRobots();
?>
@php
    use App\Models\User;
    use App\Modules\Settings\Organizations\OrganizationQuery;

    $Organization = OrganizationQuery::find(User::authenticated(request()), $organization_id);
@endphp
<x-settings-card :settingsCard="[SettingsCard::title => $Organization->name]">
    <x-status-toast/>
    <x-picture-field :pictureField="[
        PictureField::legend => 'Organization icon',
        PictureField::field => OrganizationIconRequest::icon,
        PictureField::action => Auth::settingsOrganizationIcon->url([Auth::organizationParameter => $Organization->id]),
        PictureField::picture => Picture::of($Organization, Organizations::icon, Directory::organization_icons)->url(),
        PictureField::label => $Organization->name,
    ]"/>
    <form class="mt-2 space-y-4" method="POST" action="{{Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id])}}" data-organization-form>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name, $Organization->name),
        ]"/>
        <button class="btn btn-primary">Save</button>
    </form>
</x-settings-card>
