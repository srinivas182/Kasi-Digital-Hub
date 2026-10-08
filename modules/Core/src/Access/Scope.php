<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use InvalidArgumentException;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Province;

/**
 * Where a role applies: self, an organisation, a hub, a city (municipality), a province, or national.
 */
final readonly class Scope
{
    public function __construct(public string $type, public ?string $id = null)
    {
        if (! in_array($type, ['self', 'organisation', 'hub', 'municipality', 'province', 'national'], true)) {
            throw new InvalidArgumentException("Invalid scope type [{$type}].");
        }

        if ($id === '') {
            throw new InvalidArgumentException("Scope [{$type}] needs an id.");
        }

        if (in_array($type, ['self', 'national'], true) !== ($id === null)) {
            throw new InvalidArgumentException("Scope [{$type}] ".($id === null ? 'needs an id.' : 'takes no id.'));
        }
    }

    public static function national(): self
    {
        return new self('national');
    }

    public static function self(): self
    {
        return new self('self');
    }

    public static function hub(Hub $hub): self
    {
        return new self('hub', $hub->id);
    }

    public static function municipality(Municipality $municipality): self
    {
        return new self('municipality', (string) $municipality->id);
    }

    public static function province(Province $province): self
    {
        return new self('province', (string) $province->id);
    }

    public static function organisation(Organisation $organisation): self
    {
        return new self('organisation', $organisation->id);
    }

    /**
     * Parse a human-friendly scope, as used by the console commands:
     * "national", "self", "hub:LP-GIY-TSU", "municipality:JHB", "province:LP", "organisation:<id or exact name>".
     */
    public static function parse(string $value): self
    {
        if (in_array($value, ['national', 'self'], true)) {
            return new self($value);
        }

        [$type, $key] = array_pad(explode(':', $value, 2), 2, '');

        return match ($type) {
            'hub' => self::hub(Hub::query()->where('code', $key)->orWhere('id', $key)->firstOrFail()),
            'municipality', 'city' => self::municipality(Municipality::query()->where('code', $key)->firstOrFail()),
            'province' => self::province(Province::query()->where('code', $key)->firstOrFail()),
            'organisation', 'org' => self::organisation(Organisation::query()->where('id', $key)->orWhere('name', $key)->firstOrFail()),
            default => throw new InvalidArgumentException("Invalid scope [{$value}]."),
        };
    }

    /** Whether the hub, city, province or organisation this scope points at exists. */
    public function exists(): bool
    {
        return match ($this->type) {
            'self', 'national' => true,
            'hub' => Hub::query()->whereKey($this->id)->exists(),
            'municipality' => Municipality::query()->whereKey($this->id)->exists(),
            'province' => Province::query()->whereKey($this->id)->exists(),
            'organisation' => Organisation::query()->whereKey($this->id)->exists(),
            default => false,
        };
    }

    public function describe(): string
    {
        return match ($this->type) {
            'national' => 'National',
            'self' => 'Own account',
            'hub' => Hub::query()->whereKey($this->id)->value('name') ?? 'Unknown hub',
            'municipality' => Municipality::query()->whereKey($this->id)->value('name') ?? 'Unknown city',
            'province' => Province::query()->whereKey($this->id)->value('name') ?? 'Unknown province',
            'organisation' => Organisation::query()->whereKey($this->id)->value('name') ?? 'Unknown organisation',
            default => throw new InvalidArgumentException("Invalid scope type [{$this->type}]."),
        };
    }
}
