<?php

use App\Helpers\Directory;
use App\Helpers\Picture;
use App\Modules\Projects\ProjectForm;
use App\Modules\Projects\ProjectIconRequest;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Projects;
use App\View\DataModels\ContextCard;
use App\View\DataModels\PictureField;
use App\View\DataModels\TextInput;
use Laravel\Head\Facades\Head;

Head::title('Project Settings')
    ->description('Update this project\'s name and icon.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\MemberRole;
    use App\Modules\Contexts\Authorize;

    $Project = Authorize::project(MemberRole::admin);
    $parameters = ContextRoute::parameters();
    $settings = ContextRoute::projectSettings->url($parameters);
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Project->name, ContextCard::title => 'Project Settings']">
    <x-status-toast/>
    <x-picture-field :pictureField="[
        PictureField::legend => 'Project icon',
        PictureField::field => ProjectIconRequest::icon,
        PictureField::action => ContextRoute::projectIcon->url($parameters),
        PictureField::picture => Picture::of($Project, Projects::icon, Directory::project_icons)->url(),
        PictureField::label => $Project->name,
    ]"/>
    <form class="mt-2 max-w-md space-y-4" method="POST" action="{{$settings}}" data-project-form>
        @csrf
        <x-text-input :textInput="[
            ...ProjectForm::textInput(ProjectForm::name),
            TextInput::value => old(ProjectForm::name, $Project->name),
        ]"/>
        <button class="btn btn-primary">Save</button>
    </form>
    <form class="mt-4" method="POST" action="{{$settings}}"
          onsubmit="return confirm('Delete this project? Everything recorded against it goes with it.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-error" data-project-delete>Delete</button>
    </form>
</x-context-card>
