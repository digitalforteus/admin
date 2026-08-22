<?php

namespace App\Modules\Organizations\Connections;

use App\Models\Connection;
use App\Modules\Connections\ConnectionPlugin;
use App\Sources\Db\App\Connections;
use App\View\DataModels\TextInput;

/**
 * The partition of one submitted form into the two columns a connection is kept in.
 *
 * The plugin's declaration is the allow-list, and every method here iterates it
 * rather than what arrived: a request carries whatever its sender chose to send, so
 * a partition driven off the submitted keys writes unreviewed json into the column
 * held in the clear and calls it configuration. Which of the declared fields are
 * secret is the plugin's answer too, never the sender's. On a second write a blank
 * secret means unchanged rather than cleared, because the form is forbidden to
 * render a stored secret back and so cannot submit one: reading the blank as an
 * instruction would revoke the credential every time somebody corrected the name.
 */
readonly class ConnectionFields
{
    /** @return list<string> */
    public static function declared(ConnectionPlugin $ConnectionPlugin): array
    {
        return array_map(
            static fn (mixed $name): string => is_string($name) ? $name : '',
            array_column($ConnectionPlugin->form(), TextInput::name),
        );
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @return array{credentials: array<string, string>, config: array<string, string>}
     */
    public static function split(ConnectionPlugin $ConnectionPlugin, array $submitted): array
    {
        $credentials = [];
        $config = [];

        foreach (self::declared($ConnectionPlugin) as $field) {
            $submission = $submitted[$field] ?? '';
            $value = is_string($submission) ? $submission : '';

            if (in_array($field, $ConnectionPlugin->secrets(), true)) {
                $credentials[$field] = $value;

                continue;
            }

            $config[$field] = $value;
        }

        return [
            Connections::credentials->value => $credentials,
            Connections::config->value => $config,
        ];
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @return array{credentials: array<string, string>, config: array<string, string>}
     */
    public static function merge(ConnectionPlugin $ConnectionPlugin, Connection $Connection, array $submitted): array
    {
        $split = self::split($ConnectionPlugin, $submitted);
        $credentials = [];

        foreach ($split[Connections::credentials->value] as $field => $value) {
            $held = $Connection->credentials[$field] ?? '';
            $credentials[$field] = $value === '' && is_string($held) ? $held : $value;
        }

        return [
            Connections::credentials->value => $credentials,
            Connections::config->value => $split[Connections::config->value],
        ];
    }

    /**
     * @param  array{credentials: array<string, string>, config: array<string, string>}  $fields
     * @return array<string, string>
     */
    public static function values(array $fields): array
    {
        return [
            ...$fields[Connections::credentials->value],
            ...$fields[Connections::config->value],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function inputs(ConnectionPlugin $ConnectionPlugin, ?Connection $Connection = null): array
    {
        $config = $Connection->config ?? [];
        $credentials = $Connection->credentials ?? [];
        $secrets = $ConnectionPlugin->secrets();

        return array_map(static function (array $input) use ($secrets, $config, $credentials): array {
            $declared = $input[TextInput::name] ?? '';
            $field = is_string($declared) ? $declared : '';
            $held = $credentials[$field] ?? '';
            $stored = $config[$field] ?? '';

            if (in_array($field, $secrets, true)) {
                return [
                    ...$input,
                    TextInput::configured => is_string($held) && $held !== '',
                    TextInput::required => $held === '',
                ];
            }

            return [...$input, TextInput::value => old($field, is_string($stored) ? $stored : '')];
        }, $ConnectionPlugin->form());
    }
}
