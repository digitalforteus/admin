<?php

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

test('the authenticated user is returned, and an unauthenticated request is rejected', function (): void {
    $User = User::factory()->createOne();

    $Request = Request::create('/');
    $Request->setUserResolver(fn (): User => $User);

    expect(User::authenticated($Request))->toBe($User)
        ->and(static fn () => User::authenticated(Request::create('/')))
        ->toThrow(AuthenticationException::class);
});

test('route binding reuses the authenticated user, and resolves any other from the database', function (): void {
    $User = User::factory()->createOne();
    $this->actingAs($User);
    $Other = User::factory()->createOne();

    expect((new User)->resolveRouteBinding($User->id))->toBe($User)
        ->and((new User)->resolveRouteBinding($Other->id)?->is($Other))->toBeTrue();
});
