<?php

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\View\DataModels\ContextCard;
use App\View\DataModels\InvitationRow;
use App\View\DataModels\MemberRow;
use App\View\DataModels\OrganizationMembersTable;
use Laravel\Head\Facades\Head;

Head::title('Members')
    ->description('Who this organization is shared with.')
    ->hiddenFromRobots();
?>
@php
    use App\Helpers\Depth;
    use App\Helpers\MemberRole;
    use App\Modules\Contexts\Context;
    use App\Modules\Memberships\MembershipQuery;
    use App\Modules\Organizations\InvitationQuery;

    $Organization = Context::organization();
    $Role = Context::role(Depth::organization);
    $members = array_values(array_map(
        static fn (User $Member): array => [
            MemberRow::id => $Member->id,
            MemberRow::name => $Member->name,
            MemberRow::email => $Member->email,
            MemberRow::role => MembershipQuery::carried($Member) ?? MemberRole::member,
        ],
        MembershipQuery::members(Depth::organization, $Organization)->all(),
    ));
    $invitations = array_values(array_map(
        static fn (OrganizationInvitation $Invitation): array => [
            InvitationRow::id => $Invitation->id,
            InvitationRow::email => $Invitation->email,
            InvitationRow::role => $Invitation->role,
            InvitationRow::expires_at => $Invitation->expires_at->toIso8601String(),
        ],
        InvitationQuery::pending($Organization)->all(),
    ));
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Organization->name, ContextCard::title => 'Members']">
    <x-status-toast/>
    <x-organization-members-table :organizationMembersTable="[
        OrganizationMembersTable::manages => $Role?->manages() ?? false,
        OrganizationMembersTable::owns => $Role === MemberRole::owner,
        OrganizationMembersTable::members => $members,
        OrganizationMembersTable::invitations => $invitations,
    ]"/>
</x-context-card>
