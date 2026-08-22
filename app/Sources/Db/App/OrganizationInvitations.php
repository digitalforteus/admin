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
        Table::name => 'organization_invitations',
        Table::collate => 'utf8mb4_unicode_ci',
        Table::indexes => [
            'organization_invitations_invited_by_foreign' => [
                self::invited_by,
            ],
            'organization_invitations_organization_id_email_unique' => [
                self::organization_id,
                self::email,
            ],
        ],
    ])]
enum OrganizationInvitations: string
{
    use HasColumn;

    #[Column([
        Column::name => self::id,
        Column::comment => 'The unique identifier of the invitation',
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
        Column::primary_key => true,
    ])]
    case id = 'id';

    #[Column([
        Column::name => self::organization_id,
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => false,
    ])]
    case organization_id = 'organization_id';

    #[Column([
        Column::name => self::email,
        Column::comment => 'The address the invitation was sent to',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case email = 'email';

    #[Column([
        Column::name => self::role,
        Column::comment => 'The role the invitation grants on acceptance',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
    ])]
    case role = 'role';

    #[Column([
        Column::name => self::token,
        Column::comment => 'The secret the acceptance link carries',
        Column::type => ColumnType::varchar->value,
        Column::length => 255,
        Column::nullable => false,
        Column::unique => true,
    ])]
    case token = 'token';

    #[Column([
        Column::name => self::expires_at,
        Column::comment => 'When the invitation stops being acceptable',
        Column::type => ColumnType::timestamp->value,
        Column::nullable => false,
    ])]
    case expires_at = 'expires_at';

    #[Column([
        Column::name => self::invited_by,
        Column::type => ColumnType::char->value,
        Column::length => 26,
        Column::nullable => true,
    ])]
    case invited_by = 'invited_by';

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
