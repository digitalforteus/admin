@props(['organizationsTable'])
@php
    use App\View\DataModels\OrganizationsTable;
    $OrganizationsTable = OrganizationsTable::from($organizationsTable);
@endphp
<div class="mt-6 flex flex-col gap-4">
    <form method="POST" action="{{ $OrganizationsTable->action() }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <div class="w-full max-w-sm">
            <x-text-input :textInput="$OrganizationsTable->nameInput()"/>
        </div>
        <button type="submit" class="btn btn-primary mb-1">Create Organization</button>
    </form>

    <div class="overflow-x-auto rounded-box border border-base-300">
        <table class="table table-zebra">
            <thead>
            <tr>
                <th class="w-0"><span class="sr-only">Icon</span></th>
                <th>Name</th>
                <th>Created</th>
                <th class="w-0"><span class="sr-only">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            @forelse($OrganizationsTable->rows() as $OrganizationRow)
                <tr data-organization-row>
                    <td>
                        <div @class(['avatar', 'avatar-placeholder' => $OrganizationRow->iconUrl() === null])>
                            <div class="w-8 rounded-full bg-neutral text-neutral-content">
                                @if($OrganizationRow->iconUrl() !== null)
                                    <img src="{{$OrganizationRow->iconUrl()}}" alt="{{$OrganizationRow->name}}" title="{{$OrganizationRow->name}}">
                                @else
                                    <span class="text-xs" title="{{$OrganizationRow->initials()}}">{{$OrganizationRow->initials()}}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap" title="{{$OrganizationRow->name}}">{{$OrganizationRow->name}}</td>
                    <td class="whitespace-nowrap">{{$OrganizationRow->createdAt()}}</td>
                    <td class="whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ $OrganizationRow->url() }}" class="btn btn-ghost btn-xs">Manage</a>
                            <form method="POST" action="{{ $OrganizationRow->url() }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr data-organizations-empty>
                    <td colspan="4" class="text-center text-base-content/70">No organizations yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
