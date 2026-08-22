<?php

use App\Models\User;
use App\Sources\Db\App\Users;
use App\View\DataModels\UserRow;
use App\View\DataModels\UsersTable;
use Zerotoprod\DataModel\PropertyRequiredException;

test('a row hydrates from the model it renders, requiring a name and an email', function (): void {
    $User = User::factory()->createOne();
    $UserRow = UserRow::from($User->toArray());

    expect($UserRow->name)->toBe($User->name)
        ->and($UserRow->email)->toBe($User->email)
        ->and(static fn () => UserRow::from([UserRow::name => 'Ada Lovelace']))
        ->toThrow(PropertyRequiredException::class);
});

test('the cells line up with the headings, dating a timestamp and dashing an absent one', function (): void {
    $Unverified = UserRow::from(User::factory()->unverified()->createOne()->toArray());

    expect($Unverified->cell(Users::email_verified_at))->toBe('—')
        ->and($Unverified->cell(Users::created_at))->toBe(now()->toFormattedDateString());

    $User = User::factory()->createOne();
    $cells = UserRow::from($User->toArray())->cells();
    $lastSessionAt = now()->subHour();

    expect($cells)->toHaveCount(count(UsersTable::columns()) + 1)
        ->and($cells[0])->toBe($User->name)
        ->and($cells[1])->toBe($User->email)
        ->and(UserRow::from($User->toArray())->lastSession())->toBe('—')
        ->and(UserRow::from([
            ...$User->toArray(),
            UserRow::last_session_at => $lastSessionAt->timestamp,
        ])->lastSession())->toBe($lastSessionAt->diffForHumans());
});

test('initials come from the first and last word, falling back to a mark, and the avatar to gravatar', function (): void {
    $User = User::factory()->createOne([
        Users::name->value => 'Ada Byron Lovelace',
        Users::email->value => 'MyEmailAddress@example.com',
    ]);
    $attributes = $User->toArray();

    expect(UserRow::from($attributes)->initials())->toBe('AL')
        ->and(UserRow::from([...$attributes, Users::name->value => ''])->initials())->toBe('?')
        ->and(UserRow::from($attributes)->picture())
        ->toBe('https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=80&d=404&r=g');
});
