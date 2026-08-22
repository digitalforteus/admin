<?php

namespace App\View\DataModels;

/**
 * The navigations this application renders, one case per enum.
 *
 * A case here is the whole of registering a navigation, and a single query answers
 * which one a request is under: it reads the cases in order and returns the first that
 * reports itself visible. Precedence is therefore the order these cases are declared
 * in and nowhere else — one that must yield to another is declared after it, and none
 * of them needs to know its siblings exist. The last is the fallback, so its condition
 * is the widest. The rail a case renders is found by the case's own name, so renaming
 * one silently renders nothing, and a navigation this does not name is rendered
 * nowhere, wherever it lives.
 */
enum Nav: string
{
    case admin = AdminNav::class;
    case settings = SettingsNav::class;
    case docs = DocsNav::class;
    case organization = OrganizationNav::class;
    case left = LeftNav::class;

    public static function active(): ?self
    {
        foreach (self::cases() as $Nav) {
            if ($Nav->visible()) {
                return $Nav;
            }
        }

        return null;
    }

    public function visible(): bool
    {
        return $this->enum()::visible();
    }

    /** @return list<NavItem> */
    public function items(): array
    {
        return $this->enum()::items();
    }

    /** @return array<string, mixed> */
    public function navRail(): array
    {
        return [
            NavRail::label => $this->enum()::label(),
            NavRail::items => $this->items(),
        ];
    }

    /** @return class-string<DescribesNav> */
    public function enum(): string
    {
        /** @var class-string<DescribesNav> */
        return $this->value;
    }
}
