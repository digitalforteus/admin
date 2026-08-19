@php
    use App\View\DataModels\Main;
    $Main = Main::from($main);
@endphp
<!doctype html>
<html lang="{{str_replace('_', '-', app()->getLocale())}}"@if($Main->theme) data-theme="{{$Main->theme}}"@endif>
<head>
  <x-google-tag/>
  <x-google-tag-manager/>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{csrf_token()}}">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
  @head
  @vite('resources/css/app.css')
</head>
<body class="h-screen overflow-y-scroll scrollbar-visible {{$Main->classnames}}">
<x-google-tag-manager-noscript/>
<x-topnav :topnav="$Main->topnav()"/>
@if($Main->adminNav)
  <x-admin-nav/>
@elseif($Main->settingsNav)
  <x-settings-nav/>
@endif
<div @class(['mt-16', 'lg:pl-56' => $Main->nav()])>
  <div class="min-h-[calc(100vh-4rem)]">{{$slot}}</div>
</div>
<x-footer/>
@vite('resources/js/app.js')
</body>
</html>
