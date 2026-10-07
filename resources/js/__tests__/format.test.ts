import cases from '../../../tests/fixtures/format-cases.json';
import { formatDate, formatDateTime, formatMoney, formatPhone, normalisePhone } from '../lib/format';

// The same fixture is used by tests/Unit/SaFormatTest.php so PHP and TypeScript always agree.
describe('South African formats', () => {
    it.each(cases.money)('money %s (whole rands: %s) -> %s', (cents, whole, expected) => {
        expect(formatMoney(cents as number, { wholeRands: whole as boolean })).toBe(expected);
    });

    it.each(cases.phones)('phone %s -> %s', (input, normalised, display) => {
        expect(normalisePhone(input as string)).toBe(normalised);
        if (normalised) expect(formatPhone(normalised)).toBe(display);
    });

    it.each(cases.dates)('date %s -> %s / %s', (iso, date, dateTime) => {
        expect(formatDate(iso)).toBe(date);
        expect(formatDateTime(iso)).toBe(dateTime);
    });
});
