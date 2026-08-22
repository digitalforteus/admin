<?php

use App\Modules\Api\Support\ErrorCode;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\RequestStub;

test('an error response carries its status, its code and whatever data it was given', function (): void {
    expect(api_response()->notFound(ErrorCode::unauthorized, ['id' => 1]))
        ->getStatusCode()->toBe(404)
        ->and(api_response()->notFound(ErrorCode::unauthorized, ['id' => 1])->getData(true))->toBe([
            'success' => false,
            'message' => ErrorCode::unauthorized->value,
            'errors' => [ErrorCode::unauthorized->value],
            'data' => ['id' => 1],
            'type' => 'error',
        ]);

    $Conflict = api_response()->conflict(ErrorCode::missing_ability);

    expect($Conflict->getStatusCode())->toBe(409)
        ->and($Conflict->getData(true))->toBe([
            'success' => false,
            'message' => ErrorCode::missing_ability->value,
            'errors' => [ErrorCode::missing_ability->value],
            'type' => 'error',
        ]);

    $Unsupported = api_response()->unsupportedMediaType(ErrorCode::unsupported_media_type);

    expect($Unsupported->getStatusCode())->toBe(415)
        ->and($Unsupported->getData(true))->toBe([
            'success' => false,
            'message' => ErrorCode::unsupported_media_type->value,
            'errors' => [ErrorCode::unsupported_media_type->value],
            'type' => 'error',
        ]);

    $Unprocessable = api_response()->unprocessableEntity(Validator::make([], ['name' => 'required']));

    expect($Unprocessable->getStatusCode())->toBe(422)
        ->and($Unprocessable->getData(true))->toBe([
            'success' => false,
            'message' => 'unprocessable entity',
            'errors' => ['name' => ['The name field is required.']],
            'type' => 'error',
        ]);
});

test('a success response carries its status, and an array payload has no type', function (): void {
    $Created = api_response()->created(['id' => 1]);

    expect($Created->getStatusCode())->toBe(201)
        ->and($Created->getData(true))->toBe([
            'success' => true,
            'data' => ['id' => 1],
        ])
        ->and(api_response()->ok(['id' => 1])->getData(true))->toBe([
            'success' => true,
            'data' => ['id' => 1],
        ]);
});

test('fields filter flat keys, lists of records, nested objects, and a payload object', function (): void {
    $data = [
        'id' => 1,
        'secret' => 'hidden',
        'items' => [['name' => 'one', 'secret' => 'hidden'], ['name' => 'two', 'secret' => 'hidden']],
        'profile' => (object) ['city' => 'Fort Wayne', 'secret' => 'hidden'],
    ];

    expect(api_response()->created($data, [
        'id',
        'absent',
        'items' => ['name'],
        'profile' => ['city'],
        'absent_group' => ['city'],
    ])->getData(true))->toBe([
        'success' => true,
        'data' => [
            'id' => 1,
            'items' => [['name' => 'one'], ['name' => 'two']],
            'profile' => ['city' => 'Fort Wayne'],
        ],
    ])
        ->and(api_response()->ok(RequestStub::make(), [RequestStub::website])->getData(true))->toBe([
            'success' => true,
            'message' => 'RequestStub',
            'data' => ['website' => 'https://example.com'],
            'type' => 'RequestStub',
        ]);
});
