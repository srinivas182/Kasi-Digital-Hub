import { translate } from '../lib/i18n';

describe('translate', () => {
    const strings = { 'kiosk.timeout_body': 'Signed out in :seconds seconds.', 'nav.hub.home': 'Home' };

    it('returns the string for a key', () => {
        expect(translate(strings, 'nav.hub.home')).toBe('Home');
    });

    it('replaces :placeholders', () => {
        expect(translate(strings, 'kiosk.timeout_body', { seconds: 30 })).toBe('Signed out in 30 seconds.');
    });

    it('falls back to the key when missing', () => {
        expect(translate(strings, 'missing.key')).toBe('missing.key');
    });
});
