<?php

use App\Models\User;
use App\Modules\Connections\ConnectionPlugin;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\Github\GithubForm;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Connections;
use App\View\DataModels\ConnectionRow;
use App\View\DataModels\NavItem;
use App\View\DataModels\TextInput;
use App\View\ViewDirectory;

test('the registry answers for the keys it names, answers nothing for the rest, and every plugin it names honours the contract', function (): void {
    foreach (ConnectionProvider::cases() as $Case) {
        expect($Case->name)->not->toBe($Case->value)
            ->and(class_exists($Case->value))->toBeTrue()
            ->and($Case->plugin())->toBeInstanceOf(ConnectionPlugin::class)
            ->and(ConnectionProvider::tryFromKey($Case->name))->toBe($Case);
    }

    expect(ConnectionProvider::keys())->toContain(ConnectionProvider::github->name);

    foreach ([null, '', 'stripe', 'GITHUB', GithubConnectionClass(), 'App\\Models\\User'] as $unknown) {
        expect(ConnectionProvider::tryFromKey($unknown))->toBeNull()
            ->and(ConnectionProvider::pluginFor($unknown))->toBeNull();
    }

    $Organization = memberOrganization(User::factory()->createOne());
    $Connection = organizationConnection($Organization, attributes: [
        Connections::slug->value => 'primary',
        Connections::provider->value => ConnectionProvider::github->name,
        Connections::config->value => [GithubForm::owner => 'octocat', GithubForm::repo => 'hello-world'],
        Connections::credentials->value => [GithubForm::token => 'secret-token'],
    ]);

    foreach (ConnectionProvider::cases() as $Case) {
        $Plugin = $Case->plugin();

        expect($Plugin->label())->not->toBeEmpty()
            ->and(ViewDirectory::svg->has($Plugin->icon()))->toBeTrue()
            ->and($Plugin->form())->not->toBeEmpty()
            ->and($Plugin->secrets())->not->toBeEmpty();

        $fields = [];

        foreach ($Plugin->form() as $props) {
            $TextInput = TextInput::from($props);
            $fields[] = $TextInput->name;
        }

        expect($Plugin->secrets())->each->toBeIn($fields);

        $fields = array_fill_keys($fields, 'value');

        expect($Plugin->validate($fields)->fails())->toBeFalse()
            ->and($Plugin->validate([])->fails())->toBeTrue();

        foreach ($Plugin->navItems($Organization, $Connection) as $NavItem) {
            expect($NavItem)->toBeInstanceOf(NavItem::class)
                ->and(ViewDirectory::svg->has($NavItem->icon))->toBeTrue()
                ->and($NavItem->parameters)->toBe([
                    OrganizationRoute::organizationParameter => $Organization->slug,
                    OrganizationRoute::connectionParameter => $Connection->slug,
                ])
                ->and($NavItem->url())->toContain($Organization->slug, $Connection->slug)
                // A secret must not reach a plugin's own navigation.
                ->and($NavItem->url())->not->toContain('secret-token')
                ->and($NavItem->label)->not->toContain('secret-token');
        }
    }

    $Enterprise = $Connection->enterprise;

    expect($Enterprise->id)->toBe($Organization->enterprise_id)
        ->and(collect($Connection->organizations()->get())->pluck('id')->all())->toBe([$Organization->id])
        ->and(collect($Enterprise->connections()->get())->pluck('id')->all())->toBe([$Connection->id])
        ->and(collect($Enterprise->organizations()->get())->pluck('id')->all())->toBe([$Organization->id]);

    $stored = $Connection->refresh()->getRawOriginal(Connections::credentials->value);

    expect($stored)->toBeString()
        ->and($stored)->not->toContain('secret-token')
        ->and($Connection->credentials)->toBe([GithubForm::token => 'secret-token'])
        ->and($Connection->toArray())->not->toHaveKey(Connections::credentials->value)
        ->and(json_encode($Connection))->not->toContain('secret-token');

    $Row = ConnectionRow::from([
        ConnectionRow::organization => $Organization->slug,
        ConnectionRow::name => 'Retired Provider',
        ConnectionRow::slug => 'retired',
        ConnectionRow::provider => 'stripe',
        ConnectionRow::enabled => true,
    ]);

    expect($Row->available())->toBeFalse()
        ->and($Row->label())->toBe('stripe')
        ->and(ViewDirectory::svg->has($Row->icon()))->toBeTrue();

    $Known = ConnectionRow::from([
        ConnectionRow::organization => $Organization->slug,
        ConnectionRow::name => 'Primary',
        ConnectionRow::slug => 'primary',
        ConnectionRow::provider => ConnectionProvider::github->name,
        ConnectionRow::enabled => true,
    ]);

    expect($Known->available())->toBeTrue()
        ->and($Known->label())->toBe('GitHub')
        ->and($Known->url())->toContain($Organization->slug, 'primary')
        ->and($Known->enabledUrl())->toContain('connections', 'enabled')
        ->and($Known->manageUrl())->toContain('connections', 'primary');
});

function GithubConnectionClass(): string
{
    return ConnectionProvider::github->value;
}
