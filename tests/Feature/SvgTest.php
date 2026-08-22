<?php

use App\Helpers\SvgName;
use App\View\DataModels\Svg;
use App\View\DataModels\TextInput;
use Zerotoprod\DataModel\PropertyRequiredException;

test('a name is required, the classname defaults empty, and a text input projects its icon props', function (): void {
    $Svg = Svg::from([Svg::name => SvgName::email]);

    expect($Svg->name)->toBe(SvgName::email)
        ->and($Svg->classname)->toBeEmpty()
        ->and(static fn () => Svg::from([]))->toThrow(PropertyRequiredException::class);

    $Projected = Svg::from(TextInput::from([TextInput::name => 'email', TextInput::icon => SvgName::email])->svg());

    expect($Projected->name)->toBe(SvgName::email)
        ->and($Projected->classname)->toBe('h-4 w-4 opacity-70');
});
