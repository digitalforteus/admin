<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

readonly class BreadcrumbField
{
    use DataModel;

    public const string name = 'name';

    #[Describe([Describe::required => true])]
    public string $name;

    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string placeholder = 'placeholder';

    #[Describe([Describe::default => ''])]
    public string $placeholder;

    /**
     * @param  array<string, mixed>  $textInput
     * @return array<string, mixed>
     */
    public static function of(array $textInput): array
    {
        $TextInput = TextInput::from($textInput);

        return [
            self::name => $TextInput->name,
            self::label => $TextInput->legend ?? $TextInput->name,
            self::placeholder => $TextInput->placeholder ?? '',
        ];
    }
}
