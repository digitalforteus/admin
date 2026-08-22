<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

class NavLink
{
    use DataModel;

    public const string navLink = 'navLink';
    public const string url = 'url';

    #[Describe([Describe::required => true])]
    public string $url;

    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string svg = 'svg';

    /** @var array<string, mixed> */
    #[Describe([Describe::required => true])]
    public array $svg;

    public const string active = 'active';

    public bool $active = false;

    public const string classnames = 'classnames';

    public string $classnames = '';

    /** @return array<string, bool> */
    public function classes(): array
    {
        return [
            $this->classnames => $this->classnames !== '',
            'menu-active' => $this->active,
        ];
    }
}
