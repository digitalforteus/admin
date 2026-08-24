<?php

use App\Modules\Connections\ConnectionProvider;
use App\View\DataModels\ContextCard;
use Laravel\Head\Facades\Head;

Head::title('Connection')
    ->description('A connection this project has enabled.')
    ->hiddenFromRobots();
?>
@php
    use App\Modules\Contexts\Context;

    $Project = Context::project();
    $Connection = Context::connection();
    $ConnectionPlugin = ConnectionProvider::pluginFor($Connection->provider);
@endphp
<x-context-card :contextCard="[ContextCard::heading => $Project->name, ContextCard::title => $Connection->name]">
    <x-status-toast/>
    {!! $ConnectionPlugin->page($Project, $Connection)->render() !!}
</x-context-card>
