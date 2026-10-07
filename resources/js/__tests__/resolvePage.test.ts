import { pagePath } from '../lib/resolvePage';

describe('pagePath', () => {
    it('maps "<Module>/<Page>" to the module page file', () => {
        expect(pagePath('Site/Home')).toBe('../../../modules/Site/resources/js/Pages/Home.tsx');
        expect(pagePath('Work/Seeker/Matches')).toBe('../../../modules/Work/resources/js/Pages/Seeker/Matches.tsx');
    });

    it('rejects names without a module prefix', () => {
        expect(() => pagePath('Home')).toThrow(/Invalid page name/);
    });
});
