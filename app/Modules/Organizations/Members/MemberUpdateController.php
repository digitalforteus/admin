<?php

namespace App\Modules\Organizations\Members;

use App\Helpers\OrganizationRole;
use App\Models\User;
use App\Modules\Organizations\Authorize;
use App\Modules\Organizations\LastOwnerException;
use App\Modules\Organizations\MembershipQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class MemberUpdateController
{
    public function __invoke(Request $Request, string $organization, string $member): RedirectResponse
    {
        $Organization = Authorize::owns($Request);

        $MemberRequest = MemberRequest::from($Request->all());
        $Validator = Validator::make(...$MemberRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator)->withInput($MemberRequest->toArray());
        }

        $User = $Organization->users()->whereKey($member)->first();

        if (! $User instanceof User) {
            abort(404);
        }

        try {
            MembershipQuery::changeRole($Organization, $User, OrganizationRole::from($MemberRequest->role));
        } catch (LastOwnerException $Exception) {
            return back()->with('status', $Exception->getMessage());
        }

        return back()->with('status', 'Member updated.');
    }
}
