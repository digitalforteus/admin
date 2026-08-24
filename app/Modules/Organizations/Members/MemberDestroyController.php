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

readonly class MemberDestroyController
{
    public function __invoke(Request $Request, string $enterprise, string $organization, string $member): RedirectResponse
    {
        $Organization = Authorize::organization(MemberRole::owner);
        $User = MembershipQuery::members(Depth::organization, $Organization)->firstWhere('id', $member);

        if (! $User instanceof User) {
            abort(404);
        }

        try {
            MembershipQuery::remove(Depth::organization, $Organization, $User);
        } catch (LastOwnerException $Exception) {
            return back()->with('status', $Exception->getMessage());
        }

        return back()->with('status', 'Member removed.');
    }
}
