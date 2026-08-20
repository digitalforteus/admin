@props(['copyLink'])
@php
    use App\View\DataModels\CopyLink;
    $CopyLink = CopyLink::from($copyLink);
@endphp
<span class="group/copy-link inline-flex min-w-0 items-center gap-1">
    <span class="min-w-0">{{ $slot }}</span>
    <span class="group/copy-link-trigger relative inline-flex shrink-0 translate-x-1 opacity-0 transition-all duration-150 ease-out group-hover/copy-link:translate-x-0 group-hover/copy-link:opacity-100 focus-within:translate-x-0 focus-within:opacity-100">
        <button type="button"
                class="btn btn-ghost btn-xs btn-circle"
                data-copy-link-trigger
                data-copy-link-value="{{ $CopyLink->value }}"
                aria-label="{{ $CopyLink->label }}">
            <span class="inline-flex h-4 w-4 items-center justify-center" data-copy-link-icon>
                <x-svg :svg="$CopyLink->icon()"/>
            </span>
            <span class="hidden h-5 w-5 items-center justify-center rounded-full ring-2 ring-info" data-copy-link-success>
                <x-svg :svg="$CopyLink->successIcon()"/>
            </span>
        </button>
        <span class="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-neutral px-2 py-1 text-xs font-medium text-neutral-content opacity-0 transition-opacity duration-150 group-hover/copy-link-trigger:opacity-100 group-focus-within/copy-link-trigger:opacity-100"
              role="tooltip"
              data-copy-link-tooltip>{{ $CopyLink->label }}</span>
    </span>
</span>
