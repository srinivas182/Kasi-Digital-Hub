import { directionsUrl, distanceKm } from '../lib/geo';

describe('geo', () => {
    it('measures straight-line distance like the PHP version', () => {
        // Giyani to Polokwane is roughly 140 km in a straight line (same check as the PHP test).
        const km = distanceKm(-23.302, 30.718, -23.904, 29.469);
        expect(km).toBeGreaterThan(130);
        expect(km).toBeLessThan(160);
    });

    it('builds a directions link for the phone maps app', () => {
        expect(directionsUrl(-23.27, 30.78)).toBe('https://www.google.com/maps/dir/?api=1&destination=-23.27,30.78');
    });
});
