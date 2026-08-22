<?php

use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\Web;
use Illuminate\Http\Request;

test('a route is active for itself and its descendants, exact only for itself, parameters substituted', function (): void {
    $route = ['id' => '1', 'hash' => 'abc'];

    expect(Web::login->isActive(Request::create(Web::login->value)))->toBeTrue()
        ->and(Web::login->isActive(Request::create(Web::login->value.'/callback')))->toBeTrue()
        ->and(Web::login->isActive(Request::create(Web::register->value)))->toBeFalse()
        ->and(ApiRoute::user->isExact(Request::create(ApiRoute::user->value)))->toBeTrue()
        ->and(ApiRoute::user->isExact(Request::create(ApiRoute::user->value.'/callback')))->toBeFalse()
        ->and(Auth::verificationVerify->isExact(Request::create('/email/verify/1/abc'), $route))->toBeTrue()
        ->and(Auth::verificationVerify->isActive(Request::create('/email/verify/1/abc'), $route))->toBeTrue();
});
