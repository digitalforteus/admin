<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Initials;
use Zerotoprod\DataModel\Describe;

readonly class Avatar
{
    use DataModel;

    public const string name = 'name';

    #[Describe([Describe::default => ''])]
    public string $name;

    public const string picture = 'picture';

    #[Describe([Describe::nullable => true])]
    public ?string $picture;

    public const string size = 'size';

    #[Describe([Describe::default => 'w-9'])]
    public string $size;

    public const string text = 'text';

    #[Describe([Describe::default => 'text-sm'])]
    public string $text;

    public function initials(): string
    {
        return Initials::from($this->name);
    }
}
