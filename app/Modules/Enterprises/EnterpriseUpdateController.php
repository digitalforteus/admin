<?php

namespace App\Modules\Enterprises;

use App\Helpers\MemberRole;
use App\Modules\Contexts\Authorize;
use App\Sources\Db\App\Enterprises;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class EnterpriseUpdateController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $Enterprise = Authorize::enterprise(MemberRole::owner);

        $EnterpriseRequest = EnterpriseRequest::from($Request->all());
        $Validator = Validator::make(...$EnterpriseRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        $Enterprise->update([Enterprises::name->value => $EnterpriseRequest->name]);

        return back()->with('status', 'Enterprise updated.');
    }
}
