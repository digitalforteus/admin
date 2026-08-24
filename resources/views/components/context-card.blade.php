@props(['contextCard' => []])
@php
    use App\View\DataModels\ContextCard;
    $ContextCard = ContextCard::from($contextCard);
@endphp
<x-main>
    <div class="mx-auto max-w-6xl p-4 lg:p-6">
        <h1 class="text-2xl font-semibold" title="{{$ContextCard->heading}}">{{$ContextCard->heading}}</h1>
        <div class="mt-6 card bg-base-100">
            <div class="card-body">
                <x-page-header :pageHeader="$ContextCard->pageHeader()">
                    {{ $slot }}
                </x-page-header>
            </div>
        </div>
    </div>
</x-main>
