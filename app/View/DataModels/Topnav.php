<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

readonly class Topnav
{
    use DataModel;

    public const string topnav = 'topnav';
    public const string nav = 'nav';

    #[Describe([Describe::nullable => true])]
    public ?Nav $nav;

    /** @return list<NavItem> */
    public function items(): array
    {
        return $this->nav?->items() ?? [];
    }
}
