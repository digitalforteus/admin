<?php

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Modules\Settings\Organizations\OrganizationIconRequest;
use App\Routes\Auth;
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
    use App\Modules\Projects\ProjectQuery;
    use App\Modules\Organizations\MembershipQuery;
    use App\Modules\Settings\Organizations\OrganizationQuery;

    $Organization = OrganizationQuery::owned(User::authenticated(request()), $organization_id);
    $Members = MembershipQuery::members($Organization);
    $Projects = ProjectQuery::forOrganization($Organization);
    $organization = Auth::settingsOrganization->url([Auth::organizationParameter => $Organization->id]);
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
    <form class="mt-2 space-y-4" method="POST" action="{{$organization}}" data-organization-form>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name, $Organization->name),
        ]"/>
        <button class="btn btn-primary">Save</button>
    </form>
    <form class="mt-4" method="POST" action="{{$organization}}"
          onsubmit="return confirm('Delete this organization? Every membership and every project it holds goes with it.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-error" data-organization-delete>Delete</button>
    </form>
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-box border border-base-300 p-4">
            <h2 class="text-xs uppercase tracking-wider text-base-content/55">Members who lose access</h2>
            <ul class="mt-2 space-y-1">
                @foreach($Members as $Member)
                    <li data-organization-member>
                        <span title="{{$Member->name}}">{{$Member->name}}</span>
                        <span class="opacity-60" title="{{MembershipQuery::held($Member)?->value}}">{{MembershipQuery::held($Member)?->label() ?? '—'}}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-box border border-base-300 p-4">
            <h2 class="text-xs uppercase tracking-wider text-base-content/55">Projects it deletes</h2>
            <ul class="mt-2 space-y-1">
                @forelse($Projects as $Project)
                    <li data-organization-project title="{{$Project->name}}">{{$Project->name}}</li>
                @empty
                    <li data-organization-projects-empty class="text-base-content/70">None yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-settings-card>
