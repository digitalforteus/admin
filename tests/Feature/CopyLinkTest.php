<?php

use App\Helpers\SvgName;
use App\View\DataModels\CopyLink;
use App\View\DataModels\Svg;
use Zerotoprod\DataModel\PropertyRequiredException;

test('defaults are resolved from the props array', function (): void {
    $CopyLink = CopyLink::from([CopyLink::value => 'https://example.com/openapi.json']);

    expect($CopyLink->value)->toBe('https://example.com/openapi.json')
        ->and($CopyLink->label)->toBe('Copy link');
});

test('a value is required', function (): void {
    CopyLink::from([]);
})->throws(PropertyRequiredException::class);

test('it projects its icon and success icon props', function (): void {
    $CopyLink = CopyLink::from([CopyLink::value => 'https://example.com']);

    $Icon = Svg::from($CopyLink->icon());
    $SuccessIcon = Svg::from($CopyLink->successIcon());

    expect($Icon->name)->toBe(SvgName::link)
        ->and($SuccessIcon->name)->toBe(SvgName::check_circle);
});
