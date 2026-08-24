<?php

namespace App\Modules\Enterprises;

use App\Helpers\DataModel;
use App\Helpers\HasTextInput;
use App\Helpers\SvgName;
use App\View\DataModels\TextInput;

readonly class EnterpriseForm
{
    use DataModel;
    use HasTextInput;

    public const string name = 'name';

    #[TextInput([
        TextInput::legend => 'Enterprise Name',
        TextInput::icon => SvgName::city,
        TextInput::placeholder => 'Acme Holdings',
        TextInput::title => 'The name this enterprise is known by',
        TextInput::required => true,
    ])]
    public string $name;
}
