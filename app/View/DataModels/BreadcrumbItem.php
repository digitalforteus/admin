<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class BreadcrumbItem
{
    use DataModel;

    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string url = 'url';

    #[Describe([Describe::required => true])]
    public string $url;

    public const string picture = 'picture';

    public ?string $picture;

    public const string fallback = 'fallback';

    #[Describe([Describe::required => true])]
    public SvgName $fallback;

    /** @return array<string, mixed> */
    public function avatar(): array
    {
        return [
            Avatar::name => $this->label,
            Avatar::picture => $this->picture,
            Avatar::size => 'w-6',
            Avatar::fallback => $this->fallback,
        ];
    }
}
