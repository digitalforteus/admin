<?php

use App\Helpers\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Modules\Organizations\InvitationQuery;
use App\Modules\Organizations\Invitations\InvitationRequest;
use App\Modules\Organizations\LastOwnerException;
use App\Modules\Organizations\Members\MemberRequest;
use App\Modules\Organizations\MembershipQuery;
use App\Routes\OrganizationRoute;
use App\Routes\Web;
use App\Sources\Db\App\OrganizationInvitations;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Users;
use Illuminate\Support\Facades\Auth;

test('membership is invited, accepted once, changed by an owner, and never reduced below one owner', function (): void {
    $Owner = User::factory()->createOne([Users::name->value => 'Ada Lovelace']);
    $Organization = memberOrganization($Owner, attributes: [Organizations::slug->value => 'acme']);

    $parameters = [OrganizationRoute::organizationParameter => 'acme'];
    $members = OrganizationRoute::members->url($parameters);

    $this->actingAs($Owner)
        ->get($members)
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('data-member-row', false)
        ->assertSee('data-invite-member', false)
        ->assertSee('data-member-remove', false);

    // An invitation is a row of its own, because the address it names frequently
    // belongs to nobody here yet.
    $this->actingAs($Owner)
        ->from($members)
        ->post(OrganizationRoute::invitations->url($parameters), [
            InvitationRequest::email => '  NewColleague@Example.com ',
            InvitationRequest::role => OrganizationRole::admin->value,
        ])
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Invitation sent.');

    $Invitation = OrganizationInvitation::query()->sole();

    expect($Invitation->email)->toBe('newcolleague@example.com')
        ->and($Invitation->role)->toBe(OrganizationRole::admin)
        ->and($Invitation->invited_by)->toBe($Owner->id)
        ->and($Invitation->inviter?->id)->toBe($Owner->id)
        ->and($Invitation->expired())->toBeFalse();

    $this->actingAs($Owner)
        ->delete(OrganizationRoute::invitation->url([
            ...$parameters,
            OrganizationRoute::invitationParameter => 'missing',
        ]))
        ->assertNotFound();

    $this->actingAs($Owner)
        ->get($members)
        ->assertOk()
        ->assertSee('newcolleague@example.com')
        ->assertSee('data-invitation-row', false);

    $this->actingAs($Owner)
        ->from($members)
        ->post(OrganizationRoute::invitations->url($parameters), [InvitationRequest::email => 'not-an-email'])
        ->assertSessionHasErrors([InvitationRequest::email, InvitationRequest::role]);

    // An address with no account gets one on acceptance, and is signed in as it.
    $this->forgetCredentials()
        ->get(Web::invitation->url(['token' => $Invitation->token]))
        ->assertRedirect(OrganizationRoute::index->url($parameters));

    $Invited = User::query()->where(Users::email->value, 'newcolleague@example.com')->sole();

    expect($Invited->email_verified_at)->not->toBeNull()
        ->and(MembershipQuery::role($Organization, $Invited))->toBe(OrganizationRole::admin)
        ->and(OrganizationInvitation::query()->count())->toBe(0)
        ->and(Auth::id())->toBe($Invited->id);

    // The token is spent, so following the same link again leads nowhere.
    $this->forgetCredentials()
        ->get(Web::invitation->url(['token' => $Invitation->token]))
        ->assertNotFound();

    expect($Organization->users()->whereKey($Invited->id)->count())->toBe(1);

    // Re-inviting an address issues a fresh token rather than reviving the old one.
    $Existing = User::factory()->createOne([Users::email->value => 'colleague@example.com']);

    $First = InvitationQuery::invite($Organization, $Existing->email, OrganizationRole::member, $Owner);
    $Second = InvitationQuery::invite($Organization, $Existing->email, OrganizationRole::member, $Owner);

    expect($Second->id)->toBe($First->id)
        ->and($Second->token)->not->toBe($First->token)
        ->and(OrganizationInvitation::query()->count())->toBe(1);

    // A caller already signed in claims the invitation as themselves.
    $this->forgetCredentials()
        ->actingAs($Existing)
        ->get(Web::invitation->url(['token' => $Second->token]))
        ->assertRedirect(OrganizationRoute::index->url($parameters));

    expect(MembershipQuery::role($Organization, $Existing))->toBe(OrganizationRole::member);

    // An expired invitation is not acceptable.
    $Expired = InvitationQuery::invite($Organization, 'late@example.com', OrganizationRole::member, $Owner);
    $Expired->update([OrganizationInvitations::expires_at->value => now()->subDay()]);

    $this->forgetCredentials()
        ->get(Web::invitation->url(['token' => $Expired->token]))
        ->assertNotFound();

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->from($members)
        ->delete(OrganizationRoute::invitation->url([
            ...$parameters,
            OrganizationRoute::invitationParameter => $Expired->id,
        ]))
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Invitation revoked.');

    expect(OrganizationInvitation::query()->count())->toBe(0);

    // Standing is what a change needs, and reading is not a change.
    $this->forgetCredentials()
        ->actingAs($Existing)
        ->get($members)
        ->assertOk()
        ->assertDontSee('data-member-remove', false)
        ->assertDontSee('data-invite-member', false);

    $this->actingAs($Existing)
        ->post(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Owner->id,
        ]), [MemberRequest::role => OrganizationRole::member->value])
        ->assertForbidden();

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->from($members)
        ->post(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Existing->id,
        ]), [MemberRequest::role => OrganizationRole::admin->value])
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Member updated.');

    expect(MembershipQuery::role($Organization, $Existing))->toBe(OrganizationRole::admin);

    $this->actingAs($Owner)
        ->from($members)
        ->post(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Existing->id,
        ]), [MemberRequest::role => 'emperor'])
        ->assertSessionHasErrors(MemberRequest::role);

    $Outsider = User::factory()->createOne();

    $this->actingAs($Owner)
        ->post(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Outsider->id,
        ]), [MemberRequest::role => OrganizationRole::member->value])
        ->assertNotFound();

    $this->actingAs($Owner)
        ->delete(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Outsider->id,
        ]))
        ->assertNotFound();

    // An organization has to keep somebody able to administer it, and the database
    // cannot say so — it is a count across rows, not a constraint on one.
    expect(MembershipQuery::owners($Organization))->toBe(1);

    $this->actingAs($Owner)
        ->from($members)
        ->post(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Owner->id,
        ]), [MemberRequest::role => OrganizationRole::admin->value])
        ->assertRedirect($members)
        ->assertSessionHas('status', 'An organization must keep at least one owner.');

    $this->actingAs($Owner)
        ->from($members)
        ->delete(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Owner->id,
        ]))
        ->assertSessionHas('status', 'An organization must keep at least one owner.');

    expect(MembershipQuery::role($Organization, $Owner))->toBe(OrganizationRole::owner)
        ->and(static fn () => MembershipQuery::remove($Organization, $Owner))
        ->toThrow(LastOwnerException::class)
        ->and(static fn () => MembershipQuery::changeRole($Organization, $Owner, OrganizationRole::member))
        ->toThrow(LastOwnerException::class);

    // A second owner is what makes the first removable.
    MembershipQuery::changeRole($Organization, $Existing, OrganizationRole::owner);

    expect(MembershipQuery::owners($Organization))->toBe(2);

    $this->actingAs($Owner)
        ->from($members)
        ->delete(OrganizationRoute::member->url([
            ...$parameters,
            OrganizationRoute::memberParameter => $Owner->id,
        ]))
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Member removed.');

    expect(MembershipQuery::role($Organization, $Owner))->toBeNull()
        ->and(MembershipQuery::owners($Organization))->toBe(1);

    // Losing the membership loses the page with it.
    $this->forgetCredentials()
        ->actingAs($Owner)
        ->get($members)
        ->assertNotFound();

    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($members)
        ->assertNotFound();
});
