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
        Table::name => 'organizations',
        Table::collate => 'utf8mb4_unicode_ci',
    ])]
enum Organizations: string
{
    use HasColumn;

    #[Column([
        Column::name => self::id,
        Column::comment => 'The unique identifier of the organization',
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case id = 'id';

    #[Column([
        Column::name => self::name,
        Column::comment => 'The organization name',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case name = 'name';

    #[Column([
        Column::name => self::icon,
        Column::comment => 'The path of the icon the organization uploaded',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => true,
    ])]
    case icon = 'icon';

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
