<?php

use App\Helpers\Rule;

test('a parameterised rule renders its bound, its table, and optionally its column', function (): void {
    expect(Rule::max(255))->toBe('max:255')
        ->and(Rule::unique('users'))->toBe('unique:users')
        ->and(Rule::unique('users', 'email'))->toBe('unique:users,email');
});
