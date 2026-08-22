<?php

namespace App\Modules\Organizations;

use App\Helpers\DataModel;
use App\Models\Organization;
use App\Models\User;

readonly class AcceptedInvitation
{
    use DataModel;

    public const string Organization = 'Organization';

    public Organization $Organization;

    public const string User = 'User';

    public User $User;
}
