<?php

use App\Helpers\SvgName;
use App\View\DataModels\CopyLink;
use App\View\DataModels\Svg;
use Zerotoprod\DataModel\PropertyRequiredException;

test('a value is required, the label defaults, and it projects its icon and success icon props', function (): void {
    $CopyLink = CopyLink::from([CopyLink::value => 'https://example.com/openapi.json']);

    expect($CopyLink->value)->toBe('https://example.com/openapi.json')
        ->and($CopyLink->label)->toBe('Copy link')
        ->and(static fn () => CopyLink::from([]))->toThrow(PropertyRequiredException::class)
        ->and(Svg::from($CopyLink->icon())->name)->toBe(SvgName::link)
        ->and(Svg::from($CopyLink->successIcon())->name)->toBe(SvgName::check_circle);
});
