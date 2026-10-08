<?php

declare(strict_types=1);

namespace Modules\Core\Access;

/**
 * How much a role may do in a portal (the Portals & Roles access matrix):
 * view (read-only, in scope) < use (own data) < assist (act for someone with consent) < manage.
 */
enum AccessLevel: int
{
    case View = 1;
    case Use = 2;
    case Assist = 3;
    case Manage = 4;

    public static function fromName(string $name): self
    {
        return match ($name) {
            'view' => self::View,
            'use' => self::Use,
            'assist' => self::Assist,
            'manage' => self::Manage,
            default => throw new \InvalidArgumentException("Unknown access level [{$name}]."),
        };
    }

    public function label(): string
    {
        return strtolower($this->name);
    }

    public function atLeast(self $other): bool
    {
        return $this->value >= $other->value;
    }
}
