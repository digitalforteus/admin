@props(['pictureField'])
@php
    use App\View\DataModels\PictureField;
    $PictureField = PictureField::from($pictureField);
@endphp
<x-field :fieldset="$PictureField->fieldset()">
    <div class="relative w-fit" data-picture-field>
        <div @class(['avatar', 'avatar-placeholder' => $PictureField->picture === null])>
            <div class="{{$PictureField->size}} rounded-full bg-neutral text-neutral-content">
                @if($PictureField->picture !== null)
                    <img src="{{$PictureField->picture}}" alt="{{$PictureField->label}}" title="{{$PictureField->label}}" referrerpolicy="no-referrer">
                @else
                    <span class="text-4xl" title="{{$PictureField->initials()}}">{{$PictureField->initials()}}</span>
                @endif
            </div>
        </div>
        <div class="dropdown dropdown-bottom dropdown-start absolute bottom-3 left-0">
            <div tabindex="0" role="button" class="gap-1 btn btn-sm" title="Edit {{$PictureField->legend}}">
                <x-svg :svg="$PictureField->svg()"/>
                Edit
            </div>
            <x-dropdown-menu>
                @if($PictureField->uploads)
                    <li>
                        <label for="{{$PictureField->field}}" class="my-1 font-medium">Upload a photo...</label>
                    </li>
                @else
                    <li class="menu-disabled" title="Uploading needs a storage service that keeps the file">
                        <span class="my-1 font-medium">Upload a photo...</span>
                    </li>
                @endif
                <li>
                    <button type="submit" form="{{$PictureField->remove()}}" class="my-1 font-medium">Remove photo</button>
                </li>
            </x-dropdown-menu>
        </div>
    </div>
    @if($PictureField->uploads)
        <form class="hidden" method="POST" action="{{$PictureField->action}}" enctype="multipart/form-data" data-picture-field-form>
            @csrf
            <input id="{{$PictureField->field}}" name="{{$PictureField->field}}" type="file"
                   accept="{{$PictureField->accept}}" onchange="this.form.submit()"/>
        </form>
    @endif
    <form class="hidden" id="{{$PictureField->remove()}}" method="POST" action="{{$PictureField->action}}">
        @csrf
        @method('DELETE')
    </form>
</x-field>
