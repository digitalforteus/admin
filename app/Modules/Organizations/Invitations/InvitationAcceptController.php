<?php

namespace App\Modules\Organizations\Invitations;

use App\Models\User;
use App\Modules\Organizations\InvitationQuery;
use App\Routes\OrganizationRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

readonly class InvitationAcceptController
{
    public function __invoke(Request $Request, string $token): RedirectResponse
    {
        $User = $Request->user();

        $Accepted = InvitationQuery::accept($token, $User instanceof User ? $User : null);

        if (! $User instanceof User) {
            Auth::login($Accepted->User);
            $Request->session()->regenerate();
        }

        return redirect(OrganizationRoute::index->url([
            OrganizationRoute::organizationParameter => $Accepted->Organization->slug,
        ]));
    }
}
