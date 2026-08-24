<?php

namespace App\Modules\Enterprises;

use App\Helpers\Slug;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\OrganizationCreator;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Enterprises;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class EnterpriseController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $EnterpriseCreateRequest = EnterpriseCreateRequest::from($Request->all());
        $Validator = Validator::make(...$EnterpriseCreateRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($EnterpriseCreateRequest->toArray());
        }

        $User = User::authenticated($Request);

        $Organization = Organization::query()->getConnection()->transaction(
            static fn (): Organization => OrganizationCreator::create(
                self::enterprise($EnterpriseCreateRequest->name),
                $EnterpriseCreateRequest->organization,
                $User,
            ),
        );

        return redirect()
            ->to(OrganizationRoute::index->url([OrganizationRoute::organizationParameter => $Organization->slug]))
            ->with('status', 'Enterprise created.');
    }

    private static function enterprise(string $name): Enterprise
    {
        return Enterprise::query()->create([
            Enterprises::name->value => $name,
            Enterprises::slug->value => Slug::unique(Enterprise::class, Enterprises::slug->value, $name),
        ]);
    }
}
