<?php

use App\Helpers\SvgName;
use App\Models\User;
use App\Modules\Settings\Credentials\TokenForm;
use App\Routes\Auth;
use App\Sources\Db\App\PersonalAccessTokens;
use App\View\DataModels\CredentialRow;
use App\View\DataModels\CredentialsTable;
use App\View\DataModels\TextInput;
use App\View\ViewDirectory;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @param  array<string, mixed>  $overrides */
function credentialsTable(array $overrides = []): CredentialsTable
{
    return CredentialsTable::from([
        CredentialsTable::tokens => [],
        ...$overrides,
    ]);
}

test('the table requires a listing, and heads only real columns readable off a row', function (): void {
    expect(static fn () => CredentialsTable::from())->toThrow(PropertyRequiredException::class);

    $properties = array_keys(get_class_vars(CredentialRow::class));

    foreach (CredentialsTable::columns() as $Column) {
        expect(PersonalAccessTokens::tryFrom($Column->value))->toBe($Column)
            ->and($properties)->toContain($Column->value);
    }

    expect(CredentialsTable::columns())->not->toContain(PersonalAccessTokens::token);

    $headers = credentialsTable()->headers();

    expect($headers)->toHaveSameSize(CredentialsTable::columns())
        ->and($headers['Name'])->toBe(PersonalAccessTokens::name->comment())
        // A timestamp is headed without the suffix its column name carries.
        ->and(array_keys($headers))->toContain('Last Used', 'Expires', 'Created')
        ->and($headers['Expires'])->toBe(PersonalAccessTokens::expires_at->comment())
        // The empty row spans the headings and the revoke column.
        ->and(credentialsTable()->span())->toBe(count(CredentialsTable::columns()) + 1);
});

test('the form posts to the page it is on, with the inputs it declares and an expiry a month out', function (): void {
    expect(credentialsTable()->action())->toBe(Auth::settingsCredentials->value)
        ->and(credentialsTable()->nameInput()[TextInput::name])->toBe(TokenForm::name)
        ->and(credentialsTable()->expiresAtInput()[TextInput::name])->toBe(TokenForm::expires_at)
        ->and(credentialsTable()->expiresAtInput()[TextInput::value])
        ->toBe(now()->addDays(CredentialsTable::expiryDays)->toDateString())
        ->and(TextInput::from(credentialsTable()->nameInput())->icon)->toBe(SvgName::command_line)
        ->and(ViewDirectory::svg->has(SvgName::command_line))->toBeTrue();

    $Store = new Store('test', new ArraySessionHandler(1));
    $Store->put('_old_input', [TokenForm::expires_at => '2030-01-01']);
    request()->setLaravelSession($Store);

    expect(credentialsTable()->expiresAtInput()[TextInput::value])->toBe('2030-01-01');
});

test('a secret is shown once it is flashed, and a row is built for every token given', function (): void {
    expect(credentialsTable()->issued)->toBeNull();

    session()->put(CredentialsTable::sessionKey, 'plain-text-token');

    expect(credentialsTable()->issued)->toBe('plain-text-token');

    $User = User::factory()->createOne();
    $tokens = [
        issuedToken($User, $User->createToken('newer'))->toArray(),
        issuedToken($User, $User->createToken('older'))->toArray(),
    ];

    $rows = credentialsTable([CredentialsTable::tokens => $tokens])->rows();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->name)->toBe('newer')
        ->and($rows[1]->name)->toBe('older');
});
