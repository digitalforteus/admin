<?php

namespace App\Modules\Organizations;

use App\Helpers\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Sources\Db\App\OrganizationInvitations;
use App\Sources\Db\App\Users;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class InvitationQuery
{
    /** @return Collection<int, OrganizationInvitation> */
    public static function pending(Organization $Organization): Collection
    {
        $Relation = $Organization->invitations();

        $Relation->orderBy(OrganizationInvitations::email->value);

        return $Relation->get();
    }

    public static function invite(
        Organization $Organization,
        string $email,
        OrganizationRole $OrganizationRole,
        ?User $Inviter,
    ): OrganizationInvitation {
        $Invitation = $Organization->invitations()->firstOrNew([
            OrganizationInvitations::email->value => $email,
        ]);

        $Invitation->fill([
            OrganizationInvitations::role->value => $OrganizationRole,
            OrganizationInvitations::token->value => Str::random(48),
            OrganizationInvitations::expires_at->value => now()->addWeek(),
            OrganizationInvitations::invited_by->value => $Inviter?->id,
        ])->save();

        return $Invitation;
    }

    public static function accept(string $token, ?User $User): AcceptedInvitation
    {
        return OrganizationInvitation::query()->getConnection()->transaction(static function () use ($token, $User): AcceptedInvitation {
            $Builder = OrganizationInvitation::query()
                ->where(OrganizationInvitations::token->value, $token);

            $Builder->lockForUpdate();

            $Invitation = $Builder->first();

            if (! $Invitation instanceof OrganizationInvitation || $Invitation->expired()) {
                abort(404);
            }

            $Existing = $User instanceof User ? $User : self::find($Invitation->email);
            $Member = $Existing instanceof User ? $Existing : self::create($Invitation->email);
            $Organization = $Invitation->organization;

            MembershipQuery::add($Organization, $Member, $Invitation->role);

            $Invitation->delete();

            return AcceptedInvitation::from([
                AcceptedInvitation::Organization => $Organization,
                AcceptedInvitation::User => $Member,
            ]);
        });
    }

    private static function find(string $email): ?User
    {
        return User::query()->where(Users::email->value, $email)->first();
    }

    private static function create(string $email): User
    {
        return User::query()->forceCreate([
            Users::name->value => Str::before($email, '@'),
            Users::email->value => $email,
            Users::email_verified_at->value => now(),
            Users::password->value => Str::random(64),
        ]);
    }
}
