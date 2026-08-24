<?php

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Modules\Organizations\Organizations\OrganizationIconRequest;
use App\Routes\ContextRoute;
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
    use App\Helpers\Depth;
    use App\Helpers\MemberRole;
    use App\Models\User;
    use App\Modules\Contexts\Authorize;
    use App\Modules\Contexts\DepthQuery;
    use App\Modules\Memberships\MembershipQuery;

    $Organization = Authorize::organization(MemberRole::owner);
    $Members = MembershipQuery::members(Depth::organization, $Organization);
    $Projects = DepthQuery::children(Depth::project, $Organization, User::authenticated(request()));
    $parameters = ContextRoute::parameters();
    $settings = ContextRoute::organizationSettings->url($parameters);
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Organization->name, ContextCard::title => 'Organization Settings']">
    <x-status-toast/>
    <x-picture-field :pictureField="[
        PictureField::legend => 'Organization icon',
        PictureField::field => OrganizationIconRequest::icon,
        PictureField::action => ContextRoute::organizationIcon->url($parameters),
        PictureField::picture => Picture::of($Organization, Organizations::icon, Directory::organization_icons)->url(),
        PictureField::label => $Organization->name,
    ]"/>
    <form class="mt-2 max-w-md space-y-4" method="POST" action="{{$settings}}" data-organization-form>
        @csrf
        <x-text-input :textInput="[
            ...OrganizationForm::textInput(OrganizationForm::name),
            TextInput::value => old(OrganizationForm::name, $Organization->name),
        ]"/>
        <button class="btn btn-primary">Save</button>
    </form>
    <form class="mt-4" method="POST" action="{{$settings}}"
          onsubmit="return confirm('Delete this organization? Every membership and every project it holds goes with it.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-error" data-organization-delete>Delete</button>
    </form>
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-box border border-base-300 p-4">
            <h2 class="text-xs uppercase tracking-wider text-base-content/55">Created by</h2>
            <p class="mt-1" data-organization-creator title="{{$Organization->creator?->name}}">{{$Organization->creator?->name ?? '—'}}</p>
            <h2 class="mt-4 text-xs uppercase tracking-wider text-base-content/55">Members who lose access</h2>
            <ul class="mt-2 space-y-1">
                @foreach($Members as $Member)
                    <li data-organization-member>
                        <span title="{{$Member->name}}">{{$Member->name}}</span>
                        <span class="opacity-60" title="{{MembershipQuery::carried($Member)?->value}}">{{MembershipQuery::carried($Member)?->label() ?? '—'}}</span>
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
</x-context-card>
