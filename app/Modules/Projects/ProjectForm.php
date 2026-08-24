<?php

namespace App\Modules\Projects;

use App\Helpers\DataModel;
use App\Helpers\HasTextInput;
use App\Helpers\SvgName;
use App\View\DataModels\TextInput;

readonly class ProjectForm
{
    use DataModel;
    use HasTextInput;

    public const string name = 'name';

    #[TextInput([
        TextInput::legend => 'Project Name',
        TextInput::icon => SvgName::folder,
        TextInput::placeholder => 'Website Redesign',
        TextInput::title => 'The name this project is known by',
        TextInput::required => true,
    ])]
    public string $name;
}
