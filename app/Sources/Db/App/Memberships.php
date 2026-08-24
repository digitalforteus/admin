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
        Table::name => 'memberships',
        Table::collate => 'utf8mb4_unicode_ci',
        Table::indexes => [
            'memberships_user_id_subject_type_index' => [
                self::user_id,
                self::subject_type,
            ],
        ],
    ])]
enum Memberships: string
{
    use HasColumn;

    #[Column([
        Column::name => self::subject_type,
        Column::comment => 'The depth the standing is held at',
        Column::type => ColumnType::varchar->value,
        Column::length => 32,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case subject_type = 'subject_type';

    #[Column([
        Column::name => self::subject_id,
        Column::comment => 'The identifier of the subject the standing is held at',
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case subject_id = 'subject_id';

    #[Column([
        Column::name => self::user_id,
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case user_id = 'user_id';

    #[Column([
        Column::name => self::role,
        Column::comment => 'The standing the member holds',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case role = 'role';

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
