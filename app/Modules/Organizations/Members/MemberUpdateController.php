<?php

namespace App\Modules\Organizations\Members;

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\User;
use App\Modules\Contexts\Authorize;
use App\Modules\Memberships\LastOwnerException;
use App\Modules\Memberships\MembershipQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class MemberUpdateController
{
    public function __invoke(Request $Request, string $enterprise, string $organization, string $member): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::owner);

        $MemberRequest = MemberRequest::from($Request->all());
        $Validator = Validator::make(...$MemberRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator)->withInput($MemberRequest->toArray());
        }

        $User = MembershipQuery::members(Depth::organization, $Organization)->firstWhere('id', $member);

        if (! $User instanceof User) {
            abort(404);
        }

        try {
            MembershipQuery::change(Depth::organization, $Organization, $User, MemberRole::from($MemberRequest->role));
        } catch (LastOwnerException $Exception) {
            return back()->with('status', $Exception->getMessage());
        }

        return back()->with('status', 'Member updated.');
    }
}
