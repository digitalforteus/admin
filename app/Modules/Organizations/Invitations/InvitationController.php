<?php

namespace App\Modules\Organizations\Invitations;

use App\Helpers\MemberRole;
use App\Models\User;
use App\Modules\Contexts\Authorize;
use App\Modules\Organizations\InvitationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class InvitationController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::admin);

        $InvitationRequest = InvitationRequest::from($Request->all());
        $Validator = Validator::make(...$InvitationRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator)->withInput($InvitationRequest->toArray());
        }

        InvitationQuery::invite(
            $Organization,
            $InvitationRequest->email,
            MemberRole::from($InvitationRequest->role),
            User::authenticated($Request),
        );

        return back()->with('status', 'Invitation sent.');
    }
}
