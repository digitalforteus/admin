<?php

use App\Helpers\Rule;
use App\Modules\Login\LoginRequest;
use Tests\Fixtures\RequestStub;

test('rules, messages and attributes are collected from request metadata and handed to the validator', function (): void {
    $RequestStub = RequestStub::make();

    expect($RequestStub->rules())->toBe([
        RequestStub::website => [Rule::required->value, Rule::url->value],
        RequestStub::secret => [Rule::nullable->value, Rule::string->value],
        RequestStub::callable => [Rule::nullable->value, Rule::max(10)],
    ])
        ->and($RequestStub->messages())->toBe([
            RequestStub::website.'.'.Rule::required->value => 'A website is required.',
        ])
        ->and($RequestStub->attributes())->toBe([RequestStub::website => 'website address'])
        ->and($RequestStub->validator())->toBe([
            $RequestStub->toArray(),
            $RequestStub->rules(),
            $RequestStub->messages(),
            $RequestStub->attributes(),
        ])
        // A column definition backs the rules, and a request appends to them.
        ->and(LoginRequest::from([LoginRequest::email => 'john@example.com', LoginRequest::password => 'password'])->rules())
        ->toBe([
            LoginRequest::email => [Rule::required->value, Rule::string->value, Rule::max(255), Rule::email->value],
            LoginRequest::password => [Rule::required->value, Rule::string->value, Rule::max(255)],
        ]);
});
