<?php

use App\Helpers\SortDirection;
use App\Models\User;
use App\Modules\Admin\Users\UsersQuery;
use App\Modules\Admin\Users\UsersRequest;
use App\Routes\Admin;
use App\Sources\Db\App\Users;
use App\View\DataModels\SortableHeader;
use App\View\DataModels\TextInput;
use App\View\DataModels\UserRow;
use App\View\DataModels\UsersTable;
use App\View\ViewDirectory;
use Illuminate\Pagination\LengthAwarePaginator;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @param  array<string, mixed>  $overrides */
function usersTable(array $overrides = []): UsersTable
{
    return UsersTable::from([
        UsersTable::search => '',
        UsersTable::sort => Users::name,
        UsersTable::direction => SortDirection::asc,
        UsersTable::paginator => new LengthAwarePaginator([], 0, UsersQuery::perPage),
        ...$overrides,
    ]);
}

test('the table requires every property and heads only real columns readable off a row', function (): void {
    expect(static fn () => UsersTable::from([UsersTable::search => '', UsersTable::sort => Users::name]))
        ->toThrow(PropertyRequiredException::class);

    $properties = array_keys(get_class_vars(UserRow::class));

    foreach (UsersTable::columns() as $Column) {
        expect(Users::tryFrom($Column->value))->toBe($Column)
            ->and($properties)->toContain($Column->value);
    }

    // The span covers every column and the last session.
    expect(usersTable()->span())->toBe(count(UsersTable::columns()) + 1);
});

test('a heading carries the comment, marks the ordering, links to the opposite and keeps the term', function (): void {
    $SortableHeader = usersTable()->headers()[0];

    expect($SortableHeader->label)->toBe('Name')
        ->and($SortableHeader->title)->toBe(Users::name->comment());

    $ordered = usersTable([UsersTable::sort => Users::email, UsersTable::direction => SortDirection::desc])->headers();

    $email = collect($ordered)->firstOrFail(
        static fn (SortableHeader $SortableHeader): bool => $SortableHeader->label === 'Email'
    );

    expect($email->sorted)->toBeTrue()
        ->and($email->ariaSort())->toBe(SortDirection::desc->aria())
        ->and($email->url)->toContain(UsersRequest::direction.'='.SortDirection::asc->value);

    $name = collect(usersTable([UsersTable::sort => Users::email])->headers())->firstOrFail(
        static fn (SortableHeader $SortableHeader): bool => $SortableHeader->label === 'Name'
    );

    expect($name->sorted)->toBeFalse()
        ->and($name->ariaSort())->toBe('none')
        ->and($name->url)->toContain(UsersRequest::direction.'='.SortDirection::asc->value)
        ->and(usersTable([UsersTable::search => 'ada'])->headers()[0]->url)
        ->toContain(UsersRequest::search.'=ada')
        ->and(usersTable()->headers()[0]->url)
        ->not->toContain(UsersRequest::search.'=');

    foreach (usersTable()->headers() as $SortableHeader) {
        expect(ViewDirectory::svg->has($SortableHeader->direction->icon()))->toBeTrue();
    }
});

test('the search box keeps the term and the ordering, and rows come off the paginator', function (): void {
    $UsersTable = usersTable([UsersTable::search => 'ada']);
    $TextInput = TextInput::from($UsersTable->searchInput());

    expect($TextInput->name)->toBe(UsersRequest::search)
        ->and($TextInput->value)->toBe('ada')
        ->and($UsersTable->action())->toBe(Admin::users->url())
        ->and($UsersTable->searching())->toBeTrue()
        // The ordering is carried forward so a search does not reset it.
        ->and(usersTable([UsersTable::sort => Users::email, UsersTable::direction => SortDirection::desc])->hidden())
        ->toBe([
            UsersRequest::sort => Users::email->value,
            UsersRequest::direction => SortDirection::desc->value,
        ])
        ->and(usersTable()->summary())->toBe('No users')
        ->and(usersTable()->rows())->toBeEmpty()
        ->and(usersTable()->previousUrl())->toBeNull()
        ->and(usersTable()->nextUrl())->toBeNull();

    $User = User::factory()->createOne();

    $Paginated = usersTable([
        UsersTable::paginator => new LengthAwarePaginator([$User], 1, UsersQuery::perPage),
    ]);

    expect($Paginated->rows()[0]->name)->toBe($User->name)
        ->and($Paginated->summary())->toBe('Showing 1–1 of 1');
});
