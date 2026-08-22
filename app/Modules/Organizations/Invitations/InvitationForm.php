<?php

namespace App\Modules\Organizations\Invitations;

use App\Helpers\DataModel;
use App\Helpers\HasTextInput;
use App\Helpers\SvgName;
use App\View\DataModels\TextInput;

readonly class InvitationForm
{
    use DataModel;
    use HasTextInput;

    public const string email = 'email';

    #[TextInput([
        TextInput::legend => 'Email',
        TextInput::type => 'email',
        TextInput::icon => SvgName::email,
        TextInput::placeholder => 'colleague@example.com',
        TextInput::title => 'The address the invitation is sent to',
        TextInput::autocomplete => 'off',
        TextInput::required => true,
    ])]
    public string $email;

    public const string role = 'role';

    #[TextInput([
        TextInput::legend => 'Role',
        TextInput::title => 'The role the invitation grants on acceptance',
        TextInput::required => true,
    ])]
    public string $role;
}
