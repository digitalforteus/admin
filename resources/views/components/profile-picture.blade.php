@props(['profilePicture'])
@php
    use App\View\DataModels\ProfilePicture;
    $ProfilePicture = ProfilePicture::from($profilePicture);
@endphp
<x-field :fieldset="$ProfilePicture->fieldset()">
    <div class="relative w-fit" data-profile-picture>
        <div @class(['avatar', 'avatar-placeholder' => $ProfilePicture->picture === null])>
            <div class="w-40 rounded-full bg-neutral text-neutral-content">
                @if($ProfilePicture->picture !== null)
                    <img src="{{$ProfilePicture->picture}}" alt="{{$ProfilePicture->name}}" title="{{$ProfilePicture->name}}" referrerpolicy="no-referrer">
                @else
                    <span class="text-4xl" title="{{$ProfilePicture->initials()}}">{{$ProfilePicture->initials()}}</span>
                @endif
            </div>
        </div>
        <div class="dropdown dropdown-bottom dropdown-start absolute bottom-3 left-0">
            <div tabindex="0" role="button" class="gap-1 btn btn-sm" title="Edit profile picture">
                <x-svg :svg="$ProfilePicture->svg()"/>
                Edit
            </div>
            <x-dropdown-menu>
                <li>
                    <label for="{{$ProfilePicture->field}}" class="my-1 font-medium">Upload a photo...</label>
                </li>
                <li>
                    <button type="submit" form="{{$ProfilePicture->field}}-remove" class="my-1 font-medium">Remove photo</button>
                </li>
            </x-dropdown-menu>
        </div>
    </div>
    <form class="hidden" method="POST" action="{{$ProfilePicture->url()}}" enctype="multipart/form-data" data-profile-picture-form>
        @csrf
        <input id="{{$ProfilePicture->field}}" name="{{$ProfilePicture->field}}" type="file"
               accept="{{$ProfilePicture->accept}}" onchange="this.form.submit()"/>
    </form>
    <form class="hidden" id="{{$ProfilePicture->field}}-remove" method="POST" action="{{$ProfilePicture->url()}}">
        @csrf
        @method('DELETE')
    </form>
</x-field>
