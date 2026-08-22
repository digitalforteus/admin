<?php

namespace App\Modules\Organizations\Members;

use App\Models\User;
use App\Modules\Organizations\Authorize;
use App\Modules\Organizations\LastOwnerException;
use App\Modules\Organizations\MembershipQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class MemberDestroyController
{
    public function __invoke(Request $Request, string $organization, string $member): RedirectResponse
    {
        $Organization = Authorize::owns($Request);

        $User = $Organization->users()->whereKey($member)->first();

        if (! $User instanceof User) {
            abort(404);
        }

        try {
            MembershipQuery::remove($Organization, $User);
        } catch (LastOwnerException $Exception) {
            return back()->with('status', $Exception->getMessage());
        }

        return back()->with('status', 'Member removed.');
    }
}
