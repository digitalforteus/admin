<?php

use App\Helpers\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Modules\Organizations\InvitationQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\View\DataModels\InvitationRow;
use App\View\DataModels\MemberRow;
use App\View\DataModels\OrganizationCard;
use App\View\DataModels\OrganizationMembersTable;
use Laravel\Head\Facades\Head;

Head::title('Members')
    ->description('Who this organization is shared with.')
    ->hiddenFromRobots();
?>
@php
    $Organization = OrganizationContext::organization();
    $Role = MembershipQuery::role($Organization, request()->user());
    $members = array_values(array_map(
        static fn (User $Member): array => [
            MemberRow::id => $Member->id,
            MemberRow::name => $Member->name,
            MemberRow::email => $Member->email,
            MemberRow::role => MembershipQuery::held($Member) ?? OrganizationRole::member,
        ],
        MembershipQuery::members($Organization)->all(),
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
<x-organization-card :organizationCard="[
    OrganizationCard::organization => $Organization->name,
    OrganizationCard::title => 'Members',
]">
    <x-status-toast/>
    <x-organization-members-table :organizationMembersTable="[
        OrganizationMembersTable::organization => $Organization->slug,
        OrganizationMembersTable::manages => $Role?->manages() ?? false,
        OrganizationMembersTable::owns => $Role === OrganizationRole::owner,
        OrganizationMembersTable::members => $members,
        OrganizationMembersTable::invitations => $invitations,
    ]"/>
</x-organization-card>
