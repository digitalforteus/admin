<?php

namespace App\Modules\Settings\Organizations;

use App\Helpers\OrganizationRole;
use App\Helpers\Slug;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\MembershipQuery;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class OrganizationController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $OrganizationRequest = OrganizationRequest::from($Request->all());
        $Validator = Validator::make(...$OrganizationRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($OrganizationRequest->toArray());
        }

        $User = User::authenticated($Request);

        Organization::query()->getConnection()->transaction(static function () use ($OrganizationRequest, $User): void {
            $Enterprise = Enterprise::query()->create([
                Enterprises::name->value => $OrganizationRequest->name,
                Enterprises::slug->value => Slug::unique(Enterprise::class, Enterprises::slug->value, $OrganizationRequest->name),
            ]);

            $Organization = Organization::query()->create([
                Organizations::enterprise_id->value => $Enterprise->id,
                Organizations::name->value => $OrganizationRequest->name,
                Organizations::slug->value => Slug::unique(Organization::class, Organizations::slug->value, $OrganizationRequest->name),
                Organizations::created_by->value => $User->id,
            ]);

            MembershipQuery::add($Organization, $User, OrganizationRole::owner);
        });

        return back()->with('status', 'Organization created.');
    }
}
