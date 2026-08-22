<?php

declare(strict_types=1);

namespace App\Sources\Db\App;

use App\Sources\Db\HasColumn;
use ZeroToProd\DbModel\Column;
use ZeroToProd\DbModel\ColumnType;
use ZeroToProd\DbModel\Table;

/**
 * @method string type()
 * @method string|null comment()
 * @method int|null length()
 * @method bool|null nullable()
 * @method bool|null unique()
 * @method bool|null primary_key()
 * @method bool|null auto_increment()
 */
#[Table(
    schema: App::class,
    attributes: [
        Table::name => 'connections',
        Table::collate => 'utf8mb4_unicode_ci',
        Table::indexes => [
            'connections_enterprise_id_slug_unique' => [
                self::enterprise_id,
                self::slug,
            ],
        ],
    ])]
enum Connections: string
{
    use HasColumn;

    #[Column([
        Column::name => self::id,
        Column::comment => 'The unique identifier of the connection',
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case id = 'id';

    #[Column([
        Column::name => self::enterprise_id,
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
    ])]
    case enterprise_id = 'enterprise_id';

    #[Column([
        Column::name => self::provider,
        Column::comment => 'The registry key of the plugin that serves the connection',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case provider = 'provider';

    #[Column([
        Column::name => self::name,
        Column::comment => 'The connection name',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case name = 'name';

    #[Column([
        Column::name => self::slug,
        Column::comment => 'The url segment the connection is addressed by',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case slug = 'slug';

    #[Column([
        Column::name => self::credentials,
        Column::comment => 'The encrypted secrets the plugin declared',
        Column::type => ColumnType::text->value,
        Column::nullable => false,
    ])]
    case credentials = 'credentials';

    #[Column([
        Column::name => self::config,
        Column::comment => 'The setup values the plugin did not declare secret',
        Column::type => ColumnType::json->value,
        Column::nullable => true,
    ])]
    case config = 'config';

    #[Column([
        Column::name => self::created_at,
        Column::type => ColumnType::timestamp->value,
        Column::nullable => true,
    ])]
    case created_at = 'created_at';

    #[Column([
        Column::name => self::updated_at,
        Column::type => ColumnType::timestamp->value,
        Column::nullable => true,
    ])]
    case updated_at = 'updated_at';
}
