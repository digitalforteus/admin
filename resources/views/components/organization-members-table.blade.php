@props(['organizationMembersTable'])
@php
    use App\Helpers\SvgName;
    use App\Modules\Organizations\Invitations\InvitationRequest;
    use App\Modules\Organizations\Members\MemberRequest;
    use App\View\DataModels\Avatar;
    use App\View\DataModels\OrganizationMembersTable;
    use App\View\DataModels\TextInput;
    $OrganizationMembersTable = OrganizationMembersTable::from($organizationMembersTable);
@endphp
<div class="mt-6 flex flex-col gap-4">
    @if($OrganizationMembersTable->manages)
        <form method="POST" action="{{$OrganizationMembersTable->action()}}" class="flex flex-wrap items-end gap-2">
            @csrf
            <div class="w-full max-w-sm">
                <x-text-input :textInput="$OrganizationMembersTable->emailInput()"/>
            </div>
            <select name="{{InvitationRequest::role}}" class="select mb-1" aria-label="Invitation role">
                @foreach($OrganizationMembersTable->roles() as $OrganizationRole)
                    <option value="{{$OrganizationRole->value}}" title="{{$OrganizationRole->label()}}">{{$OrganizationRole->label()}}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary mb-1" data-invite-member>Invite</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-box border border-base-300">
        <table class="table table-zebra">
            <thead>
            <tr>
                <th class="w-0"><span class="sr-only">Avatar</span></th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th class="w-0"><span class="sr-only">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            @forelse($OrganizationMembersTable->rows() as $MemberRow)
                <tr data-member-row>
                    <td>
                        <x-avatar :avatar="[Avatar::name => $MemberRow->name, Avatar::size => 'w-8', Avatar::fallback => SvgName::user]"/>
                    </td>
                    <td class="whitespace-nowrap" title="{{$MemberRow->name}}">{{$MemberRow->name}}</td>
                    <td class="whitespace-nowrap" title="{{$MemberRow->email}}">{{$MemberRow->email}}</td>
                    <td class="whitespace-nowrap">
                        @if($OrganizationMembersTable->owns)
                            <form method="POST" action="{{$MemberRow->url()}}" class="flex items-center gap-2">
                                @csrf
                                <select name="{{MemberRequest::role}}" class="select select-sm" aria-label="Role for {{$MemberRow->email}}">
                                    @foreach($OrganizationMembersTable->roles() as $OrganizationRole)
                                        <option value="{{$OrganizationRole->value}}" @selected($OrganizationRole === $MemberRow->role) title="{{$OrganizationRole->label()}}">{{$OrganizationRole->label()}}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-ghost btn-xs" data-member-update>Save</button>
                            </form>
                        @else
                            <span title="{{$MemberRow->role->label()}}">{{$MemberRow->role->label()}}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        @if($OrganizationMembersTable->owns)
                            <form method="POST" action="{{$MemberRow->url()}}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error" data-member-remove>Remove</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr data-members-empty>
                    <td colspan="5" class="text-center text-base-content/70">No members yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($OrganizationMembersTable->pending() !== [])
        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-zebra">
                <thead>
                <tr>
                    <th>Invited</th>
                    <th>Role</th>
                    <th>Expires</th>
                    <th class="w-0"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach($OrganizationMembersTable->pending() as $InvitationRow)
                    <tr data-invitation-row>
                        <td class="whitespace-nowrap" title="{{$InvitationRow->email}}">{{$InvitationRow->email}}</td>
                        <td class="whitespace-nowrap" title="{{$InvitationRow->role->label()}}">{{$InvitationRow->role->label()}}</td>
                        <td class="whitespace-nowrap" title="{{$InvitationRow->expires_at}}">{{$InvitationRow->expires()}}</td>
                        <td class="whitespace-nowrap text-right">
                            @if($OrganizationMembersTable->manages)
                                <form method="POST" action="{{$InvitationRow->url()}}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error" data-invitation-revoke>Revoke</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
