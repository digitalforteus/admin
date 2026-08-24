<?php

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
use App\Sources\Db\App\Organizations;
use App\View\DataModels\ContextCard;
use App\View\DataModels\PictureField;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('Organization Settings')
    ->description('Update this organization\'s name and icon.')
    ->hiddenFromRobots();
?>
@php
    use App\Models\User;
    use App\Modules\Settings\Organizations\OrganizationQuery;

    $Context = App\Modules\Organizations\OrganizationContext::organization();
    $Organization = OrganizationQuery::owned(User::authenticated(request()), $Context->id);
    $organization = Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]);
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Organization->name, ContextCard::title => 'Organization Settings']">
    <x-status-toast/>
    <x-picture-field :pictureField="[
        PictureField::legend => 'Organization icon',
        PictureField::field => OrganizationIconRequest::icon,
        PictureField::action => Auth::settingsOrganizationIcon->url([Auth::organizationParameter => $Organization->id]),
        PictureField::picture => Picture::of($Organization, Organizations::icon, Directory::organization_icons)->url(),
        PictureField::label => $Organization->name,
    ]"/>
    <form class="mt-2 max-w-md space-y-4" method="POST" action="{{$organization}}" data-organization-form>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name, $Organization->name),
        ]"/>
        <button class="btn btn-primary">Save</button>
    </form>
    <form class="mt-4" method="POST" action="{{$organization}}"
          onsubmit="return confirm('Delete this organization? Every membership and every connection it has switched on goes with it.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-error" data-organization-delete>Delete</button>
    </form>
</x-context-card>
