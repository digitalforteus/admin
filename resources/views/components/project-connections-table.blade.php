@props(['projectConnectionsTable'])
@php
    use App\View\DataModels\ProjectConnectionsTable;
    $ProjectConnectionsTable = ProjectConnectionsTable::from($projectConnectionsTable);
@endphp
@if($ProjectConnectionsTable->owns)
    <div class="mt-6">
        <a class="btn btn-primary btn-sm" data-connection-add href="{{$ProjectConnectionsTable->createUrl()}}">Add connection</a>
    </div>
@endif
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
        @forelse($ProjectConnectionsTable->rows() as $ConnectionRow)
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
                    <div class="flex items-center justify-end gap-2">
                        @if($ProjectConnectionsTable->manages && $ConnectionRow->available())
                            <form method="POST" action="{{$ConnectionRow->enabledUrl()}}">
                                @csrf
                                @if($ConnectionRow->enabled)
                                    @method('DELETE')
                                @endif
                                <button type="submit" class="btn btn-ghost btn-xs" data-connection-toggle>
                                    {{ $ConnectionRow->enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        @endif
                        @if($ProjectConnectionsTable->owns)
                            <a class="btn btn-ghost btn-xs" data-connection-manage href="{{$ConnectionRow->manageUrl()}}">Manage</a>
                        @endif
                    </div>
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
