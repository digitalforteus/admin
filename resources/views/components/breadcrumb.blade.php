@props(['breadcrumb'])
@php
    use App\View\DataModels\Breadcrumb;
    $Breadcrumb = Breadcrumb::from($breadcrumb);
@endphp
<nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1 text-sm" data-breadcrumb>
    @foreach($Breadcrumb->trail() as $index => $BreadcrumbSegment)
        @if($index > 0)
            <span class="opacity-40">/</span>
        @endif
        <x-breadcrumb-segment :breadcrumbSegment="$BreadcrumbSegment->props()"/>
    @endforeach
</nav>
