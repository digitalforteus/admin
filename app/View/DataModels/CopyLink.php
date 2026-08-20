<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use Zerotoprod\DataModel\Describe;

class CopyLink
{
    use DataModel;

    public const string value = 'value';

    #[Describe([Describe::required => true])]
    public string $value;

    public const string label = 'label';

    public string $label = 'Copy link';

    /** @return array<string, mixed> */
    public function icon(): array
    {
        return [
            Svg::name => SvgName::link,
            Svg::classname => 'h-4 w-4 text-base-content/70',
        ];
    }

    /** @return array<string, mixed> */
    public function successIcon(): array
    {
        return [
            Svg::name => SvgName::check_circle,
            Svg::classname => 'h-4 w-4 text-success',
        ];
    }
}
