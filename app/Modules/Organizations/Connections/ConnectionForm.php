<?php

namespace App\Modules\Organizations\Connections;

use App\Helpers\DataModel;
use App\Helpers\HasTextInput;
use App\Helpers\SvgName;
use App\View\DataModels\TextInput;

readonly class ConnectionForm
{
    use DataModel;
    use HasTextInput;

    public const string name = 'name';

    #[TextInput([
        TextInput::legend => 'Connection Name',
        TextInput::icon => SvgName::link,
        TextInput::placeholder => 'Production Repo',
        TextInput::title => 'The name this connection is known by',
        TextInput::required => true,
    ])]
    public string $name;
}
