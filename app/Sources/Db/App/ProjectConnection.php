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
        Table::name => 'project_connection',
        Table::collate => 'utf8mb4_unicode_ci',
        Table::indexes => [
            'project_connection_connection_id_foreign' => [
                self::connection_id,
            ],
        ],
    ])]
enum ProjectConnection: string
{
    use HasColumn;

    #[Column([
        Column::name => self::project_id,
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case project_id = 'project_id';

    #[Column([
        Column::name => self::connection_id,
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case connection_id = 'connection_id';

    #[Column([
        Column::name => self::enabled_at,
        Column::comment => 'When the project enabled the connection',
        Column::type => ColumnType::timestamp->value,
        Column::nullable => true,
    ])]
    case enabled_at = 'enabled_at';

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
