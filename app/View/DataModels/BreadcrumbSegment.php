<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class BreadcrumbSegment
{
    use DataModel;

    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string url = 'url';

    public ?string $url;

    public const string picture = 'picture';

    public ?string $picture;

    public const string fallback = 'fallback';

    #[Describe([Describe::required => true])]
    public SvgName $fallback;

    public const string switchLabel = 'switchLabel';

    #[Describe([Describe::default => ''])]
    public string $switchLabel;

    public const string settingsUrl = 'settingsUrl';

    public ?string $settingsUrl;

    public const string settingsLabel = 'settingsLabel';

    #[Describe([Describe::default => ''])]
    public string $settingsLabel;

    public const string createUrl = 'createUrl';

    public ?string $createUrl;

    public const string createLabel = 'createLabel';

    #[Describe([Describe::default => ''])]
    public string $createLabel;

    public const string createAction = 'createAction';

    public ?string $createAction;

    public const string createFields = 'createFields';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $createFields;

    public const string items = 'items';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $items;

    public function settled(): bool
    {
        return $this->url !== null;
    }

    /** @return list<BreadcrumbField> */
    public function fields(): array
    {
        return array_map(
            static fn (array $field): BreadcrumbField => BreadcrumbField::from($field),
            $this->createFields,
        );
    }

    /** @return list<BreadcrumbItem> */
    public function entries(): array
    {
        return array_map(
            static fn (array $item): BreadcrumbItem => BreadcrumbItem::from($item),
            $this->items,
        );
    }

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

    /** @return array<string, mixed> */
    public function props(): array
    {
        return $this->collect()->all();
    }
}
