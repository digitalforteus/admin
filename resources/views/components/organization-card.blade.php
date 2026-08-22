@props(['organizationCard' => []])
@php
    use App\View\DataModels\OrganizationCard;
    $OrganizationCard = OrganizationCard::from($organizationCard);
@endphp
<x-main>
    <div class="mx-auto max-w-6xl p-4 lg:p-6">
        <h1 class="text-2xl font-semibold" title="{{$OrganizationCard->organization}}">{{$OrganizationCard->organization}}</h1>
        <div class="mt-6 card bg-base-100">
            <div class="card-body">
                <x-page-header :pageHeader="$OrganizationCard->pageHeader()">
                    {{ $slot }}
                </x-page-header>
            </div>
        </div>
    </div>
</x-main>
