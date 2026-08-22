<?php

use App\Models\User;
use App\Routes\Auth;
use App\Sources\Db\App\PersonalAccessTokens;
use App\View\DataModels\CredentialRow;
use App\View\DataModels\CredentialsTable;
use Illuminate\Support\Carbon;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @return array<string, mixed> */
function credentialToken(User $User, ?string $expiresAt = null): array
{
    return issuedToken(
        $User,
        $User->createToken('Laptop CLI', expiresAt: $expiresAt === null ? null : Carbon::parse($expiresAt)),
    )->toArray();
}

test('a row hydrates from the token it renders, requiring an id and a name and never the secret', function (): void {
    $User = User::factory()->createOne();
    $CredentialRow = CredentialRow::from(credentialToken($User));

    expect($CredentialRow->name)->toBe('Laptop CLI')
        ->and($CredentialRow->id)->not->toBeEmpty()
        ->and(array_keys(get_object_vars($CredentialRow)))->not->toContain(PersonalAccessTokens::token->value)
        ->and(static fn () => CredentialRow::from([CredentialRow::name => 'Laptop CLI']))
        ->toThrow(PropertyRequiredException::class);
});

test('the cells line up with the headings, dating a timestamp, dashing an absent one, and hiding the reach', function (): void {
    $CredentialRow = CredentialRow::from(credentialToken(User::factory()->createOne()));
    $cells = $CredentialRow->cells();

    expect($CredentialRow->cell(PersonalAccessTokens::last_used_at))->toBe('—')
        ->and($CredentialRow->cell(PersonalAccessTokens::expires_at))->toBe('—')
        ->and($CredentialRow->cell(PersonalAccessTokens::created_at))->toBe(now()->toFormattedDateString())
        ->and(CredentialsTable::columns())->not->toContain(PersonalAccessTokens::abilities)
        ->and($cells)->not->toContain('*')
        ->and($cells)->toHaveSameSize(CredentialsTable::columns())
        ->and($cells[0])->toBe('Laptop CLI')
        ->and($CredentialRow->url())
        ->toBe(Auth::settingsCredential->url([Auth::credentialParameter => $CredentialRow->id]));
});

test('an expiry is expired only once it has passed, and no expiry never is', function (): void {
    $User = User::factory()->createOne();

    expect(CredentialRow::from(credentialToken($User, now()->addDay()->toDateTimeString()))->expired())->toBeFalse()
        ->and(CredentialRow::from(credentialToken($User, now()->subDay()->toDateTimeString()))->expired())->toBeTrue()
        ->and(CredentialRow::from(credentialToken($User))->expired())->toBeFalse();
});
