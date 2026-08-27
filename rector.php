<?php

declare(strict_types=1);

use App\Helpers\DataModel;
use Illuminate\Support\Facades\DB;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddClosureParamTypeFromIterableMethodCallRector;
use ZeroToProd\LaravelRector\Rector\AddReadonlyToClassWithTraitRector;
use ZeroToProd\LaravelRector\Rector\AddTypeToConstOnReadonlyClassRector;
use ZeroToProd\LaravelRector\Rector\CollapseSingleLineDocblockRector;
use ZeroToProd\LaravelRector\Rector\EnforceControllerSuffixRector;
use ZeroToProd\LaravelRector\Rector\EnforceInvokableControllerRector;
use ZeroToProd\LaravelRector\Rector\EnforceInvokableControllerRouteRector;
use ZeroToProd\LaravelRector\Rector\ForbidBladeAttributeValueRector;
use ZeroToProd\LaravelRector\Rector\ForbidClassUsageRector;
use ZeroToProd\LaravelRector\Rector\ForbidCommentPhraseRector;
use ZeroToProd\LaravelRector\Rector\ForbidDuplicateBladeElementRector;
use ZeroToProd\LaravelRector\Rector\ForbidKeywordUsageRector;
use ZeroToProd\LaravelRector\Rector\RenameParamToMatchTypeExactCaseRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withRules([
        RenameVariableToMatchNewTypeRector::class,
        AddClosureParamTypeFromIterableMethodCallRector::class,
        AddTypeToConstOnReadonlyClassRector::class,
        EnforceControllerSuffixRector::class,
        EnforceInvokableControllerRector::class,
        EnforceInvokableControllerRouteRector::class,
        RenameParamToMatchTypeExactCaseRector::class,
        CollapseSingleLineDocblockRector::class,
    ])
    ->withConfiguredRule(AddReadonlyToClassWithTraitRector::class, [
        'traits' => [
            DataModel::class,
        ],
    ])
    ->withConfiguredRule(ForbidClassUsageRector::class, [
        ForbidClassUsageRector::CLASSES => [
            DB::class,
        ],
    ])
    ->withConfiguredRule(ForbidDuplicateBladeElementRector::class, [
        ForbidDuplicateBladeElementRector::ELEMENTS => [
            'title',
            'h1',
        ],
    ])
    ->withConfiguredRule(ForbidBladeAttributeValueRector::class, [
        ForbidBladeAttributeValueRector::ATTRIBUTES => [
            'href' => '#^/|(?<![>:$\w])[A-Za-z_]\w*\s*\(#',
        ],
    ])
    ->withConfiguredRule(ForbidCommentPhraseRector::class, [
        ForbidCommentPhraseRector::PHRASES => [
            '/@?todo\b/i',
        ],
    ])
    ->withConfiguredRule(ForbidKeywordUsageRector::class, [
        'keywords' => [
            'match',
        ],
        'leave_todo' => true,
        'todo_comment' => '// TODO: replace this match expression with a PHP attribute',
    ])
    ->withSets([
        PestSetList::CODING_STYLE,
    ]);
