<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Disk;
use App\Helpers\Extension;
use App\Helpers\SvgName;
use Zerotoprod\DataModel\Describe;

readonly class PictureField
{
    use DataModel;

    public const string field = 'field';

    #[Describe([Describe::required => true])]
    public string $field;

    public const string action = 'action';

    #[Describe([Describe::required => true])]
    public string $action;

    public const string legend = 'legend';

    #[Describe([Describe::default => 'Picture'])]
    public string $legend;

    public const string label = 'label';

    #[Describe([Describe::default => ''])]
    public string $label;

    public const string picture = 'picture';

    #[Describe([Describe::nullable => true])]
    public ?string $picture;

    public const string size = 'size';

    #[Describe([Describe::default => 'w-40'])]
    public string $size;

    public const string accept = 'accept';

    #[Describe([Describe::default => [Extension::class, 'imageFilter']])]
    public string $accept;

    public const string uploads = 'uploads';

    #[Describe([Describe::default => [Disk::class, 'retains']])]
    public bool $uploads;

    public const string bag = 'bag';

    #[Describe([Describe::default => 'default'])]
    public string $bag;

    /** @return array<string, mixed> */
    public function fieldset(): array
    {
        return [
            Fieldset::legend => $this->legend,
            Fieldset::name => $this->field,
            Fieldset::bag => $this->bag,
        ];
    }

    /** @return array<string, mixed> */
    public function avatar(): array
    {
        return [
            Avatar::name => $this->label,
            Avatar::picture => $this->picture,
            Avatar::size => $this->size,
            Avatar::text => 'text-4xl',
        ];
    }

    /** @return array<string, mixed> */
    public function svg(): array
    {
        return [
            Svg::name => SvgName::pencil,
            Svg::classname => 'h-4 w-4 opacity-70',
        ];
    }

    public function remove(): string
    {
        return $this->field.'-remove';
    }
}
