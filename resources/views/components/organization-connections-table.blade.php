@props(['organizationConnectionsTable'])
@php
    use App\View\DataModels\OrganizationConnectionsTable;
    $OrganizationConnectionsTable = OrganizationConnectionsTable::from($organizationConnectionsTable);
@endphp
<div class="mt-6 overflow-x-auto rounded-box border border-base-300">
    <table class="table table-zebra">
        <thead>
        <tr>
            <th>Connection</th>
            <th>Provider</th>
            <th>Status</th>
            <th class="w-0"><span class="sr-only">Actions</span></th>
        </tr>
        </thead>
        <tbody>
        @forelse($OrganizationConnectionsTable->rows() as $ConnectionRow)
            <tr data-connection-row>
                <td class="whitespace-nowrap" title="{{$ConnectionRow->name}}">
                    @if($ConnectionRow->available() && $ConnectionRow->enabled)
                        <a class="link" href="{{$ConnectionRow->url()}}">{{$ConnectionRow->name}}</a>
                    @else
                        <span @class(['opacity-50' => ! $ConnectionRow->available()])>{{$ConnectionRow->name}}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap">
                    <span class="inline-flex items-center gap-2" title="{{$ConnectionRow->label()}}">
                        <x-svg :svg="$ConnectionRow->svg()"/>
                        {{$ConnectionRow->label()}}
                    </span>
                </td>
                <td class="whitespace-nowrap">
                    @if(! $ConnectionRow->available())
                        <span class="badge badge-ghost" data-connection-unavailable title="Unavailable">Unavailable</span>
                    @elseif($ConnectionRow->enabled)
                        <span class="badge badge-success" title="Enabled">Enabled</span>
                    @else
                        <span class="badge badge-ghost" title="Disabled">Disabled</span>
                    @endif
                </td>
                <td class="whitespace-nowrap text-right">
                    @if($OrganizationConnectionsTable->manages && $ConnectionRow->available())
                        <form method="POST" action="{{$ConnectionRow->toggleUrl()}}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-xs" data-connection-toggle>
                                {{ $ConnectionRow->enabled ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr data-connections-empty>
                <td colspan="4" class="text-center text-base-content/70">No connections yet.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
