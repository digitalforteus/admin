@props(['avatar' => []])
@php
    use App\View\DataModels\Avatar;
    $Avatar = Avatar::from($avatar);
@endphp
<div class="avatar avatar-placeholder">
    <div @class(['relative rounded-full', $Avatar->size, 'bg-neutral text-neutral-content' => $Avatar->picture === null])>
        @if($Avatar->fallback !== null)
            <span @class(['flex items-center justify-center', 'hidden' => $Avatar->picture !== null]) title="{{$Avatar->name}}">
                <x-svg :svg="$Avatar->svg()"/>
            </span>
        @else
            <span @class(['hidden' => $Avatar->picture !== null, $Avatar->text]) title="{{$Avatar->initials()}}">{{$Avatar->initials()}}</span>
        @endif
        @if($Avatar->picture !== null)
            <img class="absolute inset-0" src="{{$Avatar->picture}}" alt="{{$Avatar->name}}" title="{{$Avatar->name}}"
                 referrerpolicy="no-referrer"
                 onerror="this.previousElementSibling.classList.remove('hidden'); this.parentElement.classList.add('bg-neutral', 'text-neutral-content'); this.remove()">
        @endif
    </div>
</div>
