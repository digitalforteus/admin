<?php

namespace App\Routes;

/** Which purpose a route case serves for the depth it is tagged with. */
enum ContextRouteRole
{
    case of;
    case collection;
    case create;
    case settings;
}
