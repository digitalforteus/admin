<?php

namespace App\Modules\Organizations\Invitations;

use App\Models\OrganizationInvitation;
use App\Modules\Organizations\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class InvitationDestroyController
{
    public function __invoke(Request $Request, string $organization, string $invitation): RedirectResponse
    {
        $Organization = Authorize::manages($Request);

        $Invitation = $Organization->invitations()->whereKey($invitation)->first();

        if (! $Invitation instanceof OrganizationInvitation) {
            abort(404);
        }

        $Invitation->delete();

        return back()->with('status', 'Invitation revoked.');
    }
}
