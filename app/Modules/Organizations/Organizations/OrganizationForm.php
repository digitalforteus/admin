<?php

namespace App\Modules\Organizations\Organizations;

use App\Helpers\DataModel;
use App\Helpers\HasTextInput;
use App\Helpers\SvgName;
use App\View\DataModels\TextInput;

readonly class OrganizationForm
{
    use DataModel;
    use HasTextInput;

    public const string name = 'name';

    #[TextInput([
        TextInput::legend => 'Organization Name',
        TextInput::icon => SvgName::city,
        TextInput::placeholder => 'Acme Inc.',
        TextInput::title => 'The name this organization is known by',
        TextInput::required => true,
    ])]
    public string $name;
}
