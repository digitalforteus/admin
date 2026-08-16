<?php

use App\Modules\Register\RegisterForm;
use App\Modules\Register\RegisterFormFactory;

test('the factory makes a valid register form', function (): void {
    $RegisterForm = RegisterFormFactory::factory()->make();

    expect($RegisterForm)->toBeInstanceOf(RegisterForm::class)
        ->and($RegisterForm->email)->toBe('john@example.com')
        ->and($RegisterForm->phone)->toBe('317-555-0123')
        ->and($RegisterForm->password)->toBe($RegisterForm->password_confirmation);
});
