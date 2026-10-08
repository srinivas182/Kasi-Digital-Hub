<?php

declare(strict_types=1);

namespace Modules\Site\Support;

use Modules\Core\Access\HubEntitlements;
use Modules\Core\Structure\Models\Hub;

/**
 * Hubs as shown on the public website: live hubs and those opening soon (paused hubs are hidden).
 */
final class PublicHubs
{
    /** Portals people can use at a hub, in display order. */
    private const SERVICES = ['Work', 'Learn', 'Start', 'Connect'];

    /**
     * @return list<array{slug: string, name: string, place: string|null, city: string, province: string, status: string, latitude: float|null, longitude: float|null}>
     */
    public static function all(): array
    {
        return array_values(Hub::query()
            ->whereIn('status', ['live', 'planned'])
            ->whereNotNull('slug')
            ->with(['municipality.province', 'place'])
            ->get()
            ->sortBy(fn (Hub $hub): string => $hub->municipality->province->name.'|'.$hub->municipality->name.'|'.$hub->name)
            ->map(fn (Hub $hub): array => [
                'slug' => (string) $hub->slug,
                'name' => $hub->name,
                'place' => $hub->place?->name,
                'city' => $hub->municipality->name,
                'province' => $hub->municipality->province->name,
                'status' => $hub->status,
                'latitude' => $hub->latitude !== null ? (float) $hub->latitude : null,
                'longitude' => $hub->longitude !== null ? (float) $hub->longitude : null,
            ])
            ->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        $hub = Hub::query()->whereIn('status', ['live', 'planned'])->where('slug', $slug)->with(['municipality.province', 'place'])->first();

        if ($hub === null) {
            return null;
        }

        $enabled = app(HubEntitlements::class)->modulesFor($hub);

        return [
            'slug' => (string) $hub->slug,
            'name' => $hub->name,
            'description' => $hub->description,
            'place' => $hub->place?->name,
            'city' => $hub->municipality->name,
            'province' => $hub->municipality->province->name,
            'address' => $hub->address,
            'phone' => $hub->phone,
            'email' => $hub->email,
            'status' => $hub->status,
            'openingHours' => $hub->opening_hours ?? [],
            'latitude' => $hub->latitude !== null ? (float) $hub->latitude : null,
            'longitude' => $hub->longitude !== null ? (float) $hub->longitude : null,
            'services' => array_values(array_intersect(self::SERVICES, $enabled)),
        ];
    }

    /**
     * schema.org LocalBusiness block for a hub page (helps "near me" searches).
     *
     * @param  array<string, mixed>  $hub
     * @return array<string, mixed>
     */
    public static function jsonLd(array $hub): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $hub['name'],
            'description' => $hub['description'],
            'url' => rtrim((string) config('kasi.brand.url'), '/').'/hubs/'.$hub['slug'],
            'telephone' => $hub['phone'],
            'email' => $hub['email'],
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $hub['address'],
                'addressLocality' => $hub['place'] ?? $hub['city'],
                'addressRegion' => $hub['province'],
                'addressCountry' => 'ZA',
            ]),
            'geo' => $hub['latitude'] !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $hub['latitude'], 'longitude' => $hub['longitude']] : null,
            'parentOrganization' => ['@type' => 'Organization', 'name' => config('kasi.brand.full_name')],
        ], static fn ($value): bool => $value !== null && $value !== '');
    }
}
