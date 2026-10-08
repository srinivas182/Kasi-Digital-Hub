/** Great-circle distance in km (same formula as Modules\Core\Structure\Geography::distanceKm). */
export function distanceKm(lat1: number, lng1: number, lat2: number, lng2: number): number {
    const rad = (deg: number) => (deg * Math.PI) / 180;
    const dLat = rad(lat2 - lat1);
    const dLng = rad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/** Opens the phone's own maps app (no map tiles loaded on our pages). */
export function directionsUrl(lat: number, lng: number): string {
    return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
}
