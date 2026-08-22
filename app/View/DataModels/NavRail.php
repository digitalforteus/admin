<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

readonly class NavRail
{
    use DataModel;

    public const string navRail = 'navRail';
    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string items = 'items';

    /** @var list<NavItem> */
    #[Describe([Describe::required => true])]
    public array $items;
}
