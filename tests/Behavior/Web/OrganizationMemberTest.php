<?php

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Modules\Memberships\LastOwnerException;
use App\Modules\Memberships\MembershipQuery;
use App\Modules\Organizations\InvitationQuery;
use App\Modules\Organizations\Invitations\InvitationRequest;
use App\Modules\Organizations\Members\MemberRequest;
use App\Routes\ContextRoute;
use App\Routes\Web;
use App\Sources\Db\App\OrganizationInvitations;
use App\Sources\Db\App\Organizations;
use App\Sources\Db\App\Users;
use Illuminate\Support\Facades\Auth;

test('membership is invited, accepted once, changed by an owner, and never reduced below one owner', function (): void {
    $Owner = User::factory()->createOne([Users::name->value => 'Ada Lovelace']);
    $Organization = memberOrganization($Owner, attributes: [Organizations::slug->value => 'acme']);

    $parameters = atOrganization($Organization);
    $members = ContextRoute::members->url($parameters);

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
        ->post(ContextRoute::invitations->url($parameters), [
            InvitationRequest::email => '  NewColleague@Example.com ',
            InvitationRequest::role => MemberRole::admin->value,
        ])
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Invitation sent.');

    $Invitation = OrganizationInvitation::query()->sole();

    expect($Invitation->email)->toBe('newcolleague@example.com')
        ->and($Invitation->role)->toBe(MemberRole::admin)
        ->and($Invitation->invited_by)->toBe($Owner->id)
        ->and($Invitation->inviter?->id)->toBe($Owner->id)
        ->and($Invitation->expired())->toBeFalse();

    $this->actingAs($Owner)
        ->delete(ContextRoute::invitation->url([
            ...$parameters,
            ContextRoute::invitationParameter => 'missing',
        ]))
        ->assertNotFound();

    $this->actingAs($Owner)
        ->get($members)
        ->assertOk()
        ->assertSee('newcolleague@example.com')
        ->assertSee('data-invitation-row', false);

    $this->actingAs($Owner)
        ->from($members)
        ->post(ContextRoute::invitations->url($parameters), [InvitationRequest::email => 'not-an-email'])
        ->assertSessionHasErrors([InvitationRequest::email, InvitationRequest::role]);

    // An address with no account gets one on acceptance, and is signed in as it.
    $this->forgetCredentials()
        ->get(Web::invitation->url(['token' => $Invitation->token]))
        ->assertRedirect(ContextRoute::organization->url($parameters));

    $Invited = User::query()->where(Users::email->value, 'newcolleague@example.com')->sole();

    expect($Invited->email_verified_at)->not->toBeNull()
        ->and(MembershipQuery::effective(Depth::organization, $Organization, $Invited))->toBe(MemberRole::admin)
        ->and(OrganizationInvitation::query()->count())->toBe(0)
        ->and(Auth::id())->toBe($Invited->id);

    // The token is spent, so following the same link again leads nowhere.
    $this->forgetCredentials()
        ->get(Web::invitation->url(['token' => $Invitation->token]))
        ->assertNotFound();

    expect(MembershipQuery::held(Depth::organization, $Organization, $Invited))->toBe(MemberRole::admin);

    // Re-inviting an address issues a fresh token rather than reviving the old one.
    $Existing = User::factory()->createOne([Users::email->value => 'colleague@example.com']);

    $First = InvitationQuery::invite($Organization, $Existing->email, MemberRole::member, $Owner);
    $Second = InvitationQuery::invite($Organization, $Existing->email, MemberRole::member, $Owner);

    expect($Second->id)->toBe($First->id)
        ->and($Second->token)->not->toBe($First->token)
        ->and(OrganizationInvitation::query()->count())->toBe(1);

    // A caller already signed in claims the invitation as themselves.
    $this->forgetCredentials()
        ->actingAs($Existing)
        ->get(Web::invitation->url(['token' => $Second->token]))
        ->assertRedirect(ContextRoute::organization->url($parameters));

    expect(MembershipQuery::effective(Depth::organization, $Organization, $Existing))->toBe(MemberRole::member);

    // An expired invitation is not acceptable.
    $Expired = InvitationQuery::invite($Organization, 'late@example.com', MemberRole::member, $Owner);
    $Expired->update([OrganizationInvitations::expires_at->value => now()->subDay()]);

    $this->forgetCredentials()
        ->get(Web::invitation->url(['token' => $Expired->token]))
        ->assertNotFound();

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->from($members)
        ->delete(ContextRoute::invitation->url([
            ...$parameters,
            ContextRoute::invitationParameter => $Expired->id,
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
        ->post(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Owner->id,
        ]), [MemberRequest::role => MemberRole::member->value])
        ->assertForbidden();

    $this->forgetCredentials()
        ->actingAs($Owner)
        ->from($members)
        ->post(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Existing->id,
        ]), [MemberRequest::role => MemberRole::admin->value])
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Member updated.');

    expect(MembershipQuery::effective(Depth::organization, $Organization, $Existing))->toBe(MemberRole::admin);

    $this->actingAs($Owner)
        ->from($members)
        ->post(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Existing->id,
        ]), [MemberRequest::role => 'emperor'])
        ->assertSessionHasErrors(MemberRequest::role);

    $Outsider = User::factory()->createOne();

    $this->actingAs($Owner)
        ->post(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Outsider->id,
        ]), [MemberRequest::role => MemberRole::member->value])
        ->assertNotFound();

    $this->actingAs($Owner)
        ->delete(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Outsider->id,
        ]))
        ->assertNotFound();

    // An organization has to keep somebody able to administer it, and the database
    // cannot say so — it is a count across rows, not a constraint on one.
    expect(MembershipQuery::owners(Depth::organization, $Organization))->toBe(1);

    $this->actingAs($Owner)
        ->from($members)
        ->post(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Owner->id,
        ]), [MemberRequest::role => MemberRole::admin->value])
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Organization must keep at least one owner.');

    $this->actingAs($Owner)
        ->from($members)
        ->delete(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Owner->id,
        ]))
        ->assertSessionHas('status', 'Organization must keep at least one owner.');

    expect(MembershipQuery::effective(Depth::organization, $Organization, $Owner))->toBe(MemberRole::owner)
        ->and(static fn () => MembershipQuery::remove(Depth::organization, $Organization, $Owner))
        ->toThrow(LastOwnerException::class)
        ->and(static fn () => MembershipQuery::change(Depth::organization, $Organization, $Owner, MemberRole::member))
        ->toThrow(LastOwnerException::class);

    // A second owner is what makes the first removable.
    MembershipQuery::change(Depth::organization, $Organization, $Existing, MemberRole::owner);

    expect(MembershipQuery::owners(Depth::organization, $Organization))->toBe(2);

    $this->actingAs($Owner)
        ->from($members)
        ->delete(ContextRoute::member->url([
            ...$parameters,
            ContextRoute::memberParameter => $Owner->id,
        ]))
        ->assertRedirect($members)
        ->assertSessionHas('status', 'Member removed.');

    expect(MembershipQuery::effective(Depth::organization, $Organization, $Owner))->toBeNull()
        ->and(MembershipQuery::owners(Depth::organization, $Organization))->toBe(1);

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
