@props(['navLink'])
@php
    use App\View\DataModels\NavLink;
    $NavLink = NavLink::from($navLink);
@endphp
<a href="{{$NavLink->url}}" @class($NavLink->classes())>
    <x-svg :svg="$NavLink->svg"/>
    {{$NavLink->label}}
</a>
