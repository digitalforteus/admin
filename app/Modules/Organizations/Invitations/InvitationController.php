<?php

namespace App\Modules\Organizations\Invitations;

use App\Helpers\OrganizationRole;
use App\Models\User;
use App\Modules\Organizations\Authorize;
use App\Modules\Organizations\InvitationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class InvitationController
{
    public function __invoke(Request $Request, string $organization): RedirectResponse
    {
        $Organization = Authorize::manages($Request);

        $InvitationRequest = InvitationRequest::from($Request->all());
        $Validator = Validator::make(...$InvitationRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator)->withInput($InvitationRequest->toArray());
        }

        InvitationQuery::invite(
            $Organization,
            $InvitationRequest->email,
            OrganizationRole::from($InvitationRequest->role),
            User::authenticated($Request),
        );

        return back()->with('status', 'Invitation sent.');
    }
}
