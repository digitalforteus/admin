@props(['runsTable'])
@php
    use App\View\DataModels\RunsTable;
    $RunsTable = RunsTable::from($runsTable);
@endphp
<div class="mt-6 flex flex-col gap-4">
    @if($RunsTable->failed())
        <div class="alert alert-error" data-runs-error>
            <span title="{{$RunsTable->status}}">The provider refused the request ({{$RunsTable->status}}). Check the connection's credentials.</span>
        </div>
    @else
        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-zebra">
                <thead>
                <tr>
                    <th>Status</th>
                    <th>Run</th>
                    <th>Workflow</th>
                    <th>Branch</th>
                    <th>Event</th>
                    <th>Run number</th>
                    <th>Actor</th>
                    <th>Started</th>
                </tr>
                </thead>
                <tbody>
                @forelse($RunsTable->rows() as $RunRow)
                    <tr data-run-row>
                        <td class="whitespace-nowrap">
                            <span class="badge {{$RunRow->badge()}}" title="{{$RunRow->stateLabel()}}">{{$RunRow->stateLabel()}}</span>
                        </td>
                        <td title="{{$RunRow->title}}">
                            @if($RunRow->html_url !== null)
                                <a class="link" href="{{$RunRow->html_url}}">{{$RunRow->run()}}</a>
                            @else
                                {{$RunRow->run()}}
                            @endif
                        </td>
                        <td class="whitespace-nowrap font-mono text-xs" title="{{$RunRow->workflow}}">{{$RunRow->workflow ?? '—'}}</td>
                        <td class="whitespace-nowrap" title="{{$RunRow->branch}}">{{$RunRow->branch ?? '—'}}</td>
                        <td class="whitespace-nowrap" title="{{$RunRow->event}}">{{$RunRow->event ?? '—'}}</td>
                        <td class="whitespace-nowrap" title="{{$RunRow->number}}">{{$RunRow->attemptLabel()}}</td>
                        <td class="whitespace-nowrap" title="{{$RunRow->actor}}">{{$RunRow->actor ?? '—'}}</td>
                        <td class="whitespace-nowrap" title="{{$RunRow->started}}">{{$RunRow->startedAt()}}</td>
                    </tr>
                @empty
                    <tr data-runs-empty>
                        <td colspan="{{$RunsTable->span()}}" class="text-center text-base-content/70">No workflow runs yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between">
            <span class="text-sm text-base-content/70" title="{{$RunsTable->total}}">{{$RunsTable->summary()}}</span>
            <div class="join">
                @if($RunsTable->previousUrl() !== null)
                    <a class="btn btn-sm join-item" href="{{$RunsTable->previousUrl()}}" data-runs-previous>Previous</a>
                @endif
                @if($RunsTable->nextUrl() !== null)
                    <a class="btn btn-sm join-item" href="{{$RunsTable->nextUrl()}}" data-runs-next>Next</a>
                @endif
            </div>
        </div>
    @endif
</div>
