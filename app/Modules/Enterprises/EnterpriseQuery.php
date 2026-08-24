<?php

namespace App\Modules\Enterprises;

use App\Helpers\OrganizationRole;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Sources\Db\App\Enterprises;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\OrganizationUser;
use Illuminate\Database\Eloquent\Builder;

readonly class EnterpriseQuery
{
    public static function bySlug(User $User, string $slug): Enterprise
    {
        $Enterprise = self::scoped($User)->where(Enterprises::slug->value, $slug)->first();

        if (! $Enterprise instanceof Enterprise) {
            abort(404);
        }

        return $Enterprise;
    }

    public static function owned(User $User, string $slug): Enterprise
    {
        $Enterprise = self::bySlug($User, $slug);

        if (! self::manages($Enterprise, $User)) {
            abort(403);
        }

        return $Enterprise;
    }

    /** Whether the account holds the top standing in any organization inside. */
    public static function manages(Enterprise $Enterprise, User $User): bool
    {
        return Organization::query()
            ->where(Organizations::enterprise_id->value, $Enterprise->id)
            ->whereHas('users', static fn (Builder $Builder): Builder => $Builder
                ->whereKey($User->id)
                ->where(
                    OrganizationUser::table().'.'.OrganizationUser::role->value,
                    OrganizationRole::owner->value,
                ))
            ->exists();
    }

    /** @return list<Enterprise> */
    public static function forUser(User $User): array
    {
        $Builder = self::scoped($User);

        $Builder->orderBy(Enterprises::name->value);

        return array_values($Builder->get()->all());
    }

    /** @return list<Organization> */
    public static function organizations(Enterprise $Enterprise, User $User): array
    {
        $Builder = Organization::query()
            ->where(Organizations::enterprise_id->value, $Enterprise->id)
            ->whereHas('users', static fn (Builder $Builder): Builder => $Builder->whereKey($User->id));

        $Builder->orderBy(Organizations::name->value);

        return array_values($Builder->get()->all());
    }

    /** @return Builder<Enterprise> */
    private static function scoped(User $User): Builder
    {
        return Enterprise::query()->whereHas(
            'organizations',
            static fn (Builder $Builder): Builder => $Builder->whereHas(
                'users',
                static fn (Builder $Builder): Builder => $Builder->whereKey($User->id),
            ),
        );
    }
}
