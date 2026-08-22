<?php

namespace App\Modules\Connections\Github;

use App\Helpers\DataModel;
use App\Helpers\HasTextInput;
use App\Helpers\SvgName;
use App\View\DataModels\TextInput;

readonly class GithubForm
{
    use DataModel;
    use HasTextInput;

    public const string token = 'token';

    #[TextInput([
        TextInput::legend => 'Access Token',
        TextInput::type => 'password',
        TextInput::icon => SvgName::key,
        TextInput::placeholder => 'ghp_…',
        TextInput::title => 'The token every call to the provider is made with',
        TextInput::autocomplete => 'off',
        TextInput::required => true,
        TextInput::configured => true,
        TextInput::configuredLabel => 'token',
    ])]
    public string $token;

    public const string owner = 'owner';

    #[TextInput([
        TextInput::legend => 'Owner',
        TextInput::icon => SvgName::user,
        TextInput::placeholder => 'octocat',
        TextInput::title => 'The account or organization the repository belongs to',
        TextInput::required => true,
    ])]
    public string $owner;

    public const string repo = 'repo';

    #[TextInput([
        TextInput::legend => 'Repository',
        TextInput::icon => SvgName::code,
        TextInput::placeholder => 'hello-world',
        TextInput::title => 'The repository whose workflow runs are listed',
        TextInput::required => true,
    ])]
    public string $repo;
}
