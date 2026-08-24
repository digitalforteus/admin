<?php

namespace App\Modules\Organizations\Invitations;

use App\Helpers\MemberRole;
use App\Models\OrganizationInvitation;
use App\Modules\Contexts\Authorize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class InvitationDestroyController
{
    public function __invoke(Request $Request, string $enterprise, string $organization, string $invitation): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::admin);

        $Invitation = $Organization->invitations()->whereKey($invitation)->first();

        if (! $Invitation instanceof OrganizationInvitation) {
            abort(404);
        }

        $Invitation->delete();

        return back()->with('status', 'Invitation revoked.');
    }
}
