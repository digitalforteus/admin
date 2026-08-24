<?php

namespace App\Modules\Enterprises;

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Helpers\Slug;
use App\Models\Enterprise;
use App\Models\User;
use App\Modules\Memberships\MembershipQuery;
use App\Routes\ContextRoute;
use App\Sources\Db\App\Enterprises;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class EnterpriseController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $EnterpriseRequest = EnterpriseRequest::from($Request->all());
        $Validator = Validator::make(...$EnterpriseRequest->validator());

        if ($Validator->fails()) {
            return back()
                ->withErrors($Validator)
                ->withInput($EnterpriseRequest->toArray());
        }

        $User = User::authenticated($Request);

        $Enterprise = Enterprise::query()->getConnection()->transaction(
            static function () use ($EnterpriseRequest, $User): Enterprise {
                $Enterprise = Enterprise::query()->create([
                    Enterprises::name->value => $EnterpriseRequest->name,
                    Enterprises::slug->value => Slug::unique(
                        Enterprise::class,
                        Enterprises::slug->value,
                        $EnterpriseRequest->name,
                    ),
                ]);

                MembershipQuery::grant(Depth::enterprise, $Enterprise, $User, MemberRole::owner);

                return $Enterprise;
            },
        );

        return redirect()
            ->to(ContextRoute::enterprise->url([ContextRoute::enterpriseParameter => $Enterprise->slug]))
            ->with('status', 'Enterprise created.');
    }
}
