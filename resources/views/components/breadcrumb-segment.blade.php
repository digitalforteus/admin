@props(['breadcrumbSegment'])
@php
    use App\Helpers\SvgName;
    use App\View\DataModels\BreadcrumbSegment;
    use App\View\DataModels\Svg;
    $BreadcrumbSegment = BreadcrumbSegment::from($breadcrumbSegment);
@endphp
<div class="flex items-center" data-breadcrumb-segment>
    @if($BreadcrumbSegment->settled())
        <a href="{{$BreadcrumbSegment->url}}" class="btn btn-ghost btn-sm max-w-40 gap-2 px-2"
           title="{{$BreadcrumbSegment->label}}">
            <x-avatar :avatar="$BreadcrumbSegment->avatar()"/>
            <span class="truncate">{{$BreadcrumbSegment->label}}</span>
        </a>
    @endif
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" data-breadcrumb-switcher
             @class([
                 'btn btn-ghost btn-sm',
                 'px-1' => $BreadcrumbSegment->settled(),
                 'max-w-40 gap-2 px-2 opacity-60' => ! $BreadcrumbSegment->settled(),
             ])
             title="{{$BreadcrumbSegment->switchLabel}}">
            @unless($BreadcrumbSegment->settled())
                <x-avatar :avatar="$BreadcrumbSegment->avatar()"/>
                <span class="truncate" data-breadcrumb-unsettled>{{$BreadcrumbSegment->label}}</span>
            @endunless
            <x-svg :svg="[Svg::name => SvgName::chevron_down, Svg::classname => 'h-3 w-3 opacity-60']"/>
        </div>
        <x-dropdown-menu>
            @if($BreadcrumbSegment->settled())
                <li class="menu-title flex-row items-center justify-between gap-2 pr-1">
                    <span class="truncate" title="{{$BreadcrumbSegment->label}}">{{$BreadcrumbSegment->label}}</span>
                    @if($BreadcrumbSegment->settingsUrl !== null)
                        <a href="{{$BreadcrumbSegment->settingsUrl}}" class="btn btn-ghost btn-xs px-1"
                           data-breadcrumb-settings title="{{$BreadcrumbSegment->settingsLabel}}">
                            <x-svg :svg="[Svg::name => SvgName::gear, Svg::classname => 'h-4 w-4 opacity-70']"/>
                        </a>
                    @endif
                </li>
            @else
                <li class="menu-title" title="{{$BreadcrumbSegment->switchLabel}}">{{$BreadcrumbSegment->switchLabel}}</li>
            @endif
            <li class="mx-2 my-1 border-t border-base-content/15"></li>
            @foreach($BreadcrumbSegment->entries() as $BreadcrumbItem)
                <li>
                    <a href="{{$BreadcrumbItem->url}}" class="items-center gap-3 my-1 font-medium">
                        <x-avatar :avatar="$BreadcrumbItem->avatar()"/>
                        <span class="grow truncate" title="{{$BreadcrumbItem->label}}">{{$BreadcrumbItem->label}}</span>
                    </a>
                </li>
            @endforeach
            @if($BreadcrumbSegment->createAction !== null)
                <li class="px-2 py-1">
                    <form method="POST" action="{{$BreadcrumbSegment->createAction}}"
                          class="flex flex-col gap-2 p-0 hover:bg-transparent" data-breadcrumb-form>
                        @csrf
                        @foreach($BreadcrumbSegment->fields() as $BreadcrumbField)
                            <input class="w-full input input-sm" type="text" required
                                   name="{{$BreadcrumbField->name}}"
                                   aria-label="{{$BreadcrumbField->label}}"
                                   placeholder="{{$BreadcrumbField->placeholder}}"
                                   value="{{old($BreadcrumbField->name)}}"
                                   title="{{old($BreadcrumbField->name)}}"/>
                            @error($BreadcrumbField->name)
                            <span class="text-xs text-error" title="{{$message}}">{{$message}}</span>
                            @enderror
                        @endforeach
                        <button type="submit" class="btn btn-primary btn-sm" data-breadcrumb-create>{{$BreadcrumbSegment->createLabel}}</button>
                    </form>
                </li>
            @elseif($BreadcrumbSegment->createUrl !== null)
                <li>
                    <a href="{{$BreadcrumbSegment->createUrl}}" class="items-center gap-3 my-1 font-medium" data-breadcrumb-create>
                        <x-svg :svg="[Svg::name => SvgName::plus, Svg::classname => 'h-4 w-4 opacity-70']"/>
                        <span class="grow truncate" title="{{$BreadcrumbSegment->createLabel}}">{{$BreadcrumbSegment->createLabel}}</span>
                    </a>
                </li>
            @endif
        </x-dropdown-menu>
    </div>
</div>
