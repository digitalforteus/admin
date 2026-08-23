@props(['avatar' => []])
@php
    use App\View\DataModels\Avatar;
    $Avatar = Avatar::from($avatar);
@endphp
<div class="avatar avatar-placeholder">
    <div @class(['relative rounded-full', $Avatar->size, 'bg-neutral text-neutral-content' => $Avatar->picture === null])>
        <span @class(['hidden' => $Avatar->picture !== null, $Avatar->text]) title="{{$Avatar->initials()}}">{{$Avatar->initials()}}</span>
        @if($Avatar->picture !== null)
            <img class="absolute inset-0" src="{{$Avatar->picture}}" alt="{{$Avatar->name}}" title="{{$Avatar->name}}"
                 referrerpolicy="no-referrer"
                 onerror="this.previousElementSibling.classList.remove('hidden'); this.parentElement.classList.add('bg-neutral', 'text-neutral-content'); this.remove()">
        @endif
    </div>
</div>