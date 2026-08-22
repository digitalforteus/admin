<?php

use App\Modules\Login\LoginForm;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\QueryStub;
use Tests\Fixtures\RequestStub;

test('a data model collects, serialises and sanitises what it is given, and dispatches as an event or a query', function (): void {
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

    Event::fake();

    LoginForm::from([LoginForm::email => 'john@example.com', LoginForm::password => 'password'])->dispatch();

    Event::assertDispatched(LoginForm::class);

    Event::fake();

    expect(QueryStub::get(2))->toBe(4);

    Event::assertDispatched(QueryStub::class);
});
