<?php

use App\Modules\Login\LoginForm;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\RequestStub;

test('a data model collects, serialises, converts to an array, and sanitises what it is given', function (): void {
    $LoginForm = LoginForm::from([
        LoginForm::email => 'john@example.com',
        LoginForm::password => 'password',
    ]);

    expect($LoginForm->collect()->all())->toBe([
        LoginForm::email => 'john@example.com',
        LoginForm::password => 'password',
        LoginForm::remember_token => false,
    ])
        ->and($LoginForm->toJson())->toBe(json_encode($LoginForm->collect()->all()))
        ->and($LoginForm->toArray())->toBe($LoginForm->collect()->all())
        ->and(RequestStub::sanitize("  a   b \n"))->toBe('a b')
        ->and(RequestStub::sanitizeEmail('  JOHN@Example.COM '))->toBe('john@example.com');
});

test('dispatch fires the data model as an event', function (): void {
    Event::fake();

    LoginForm::from([LoginForm::email => 'john@example.com', LoginForm::password => 'password'])->dispatch();

    Event::assertDispatched(LoginForm::class);
});
