import type { ResolvedComponent } from '@inertiajs/react';

/**
 * Resolves an Inertia page name such as "Site/Home" to the module page file
 * modules/Site/resources/js/Pages/Home.tsx. Each portal owns its pages inside
 * its own module folder (Portal SDK, ADR-001 / ADR-005).
 *
 * S26: the entry bundle no longer lists every page. Each portal has a small `pages.ts` registry; the registries
 * on the first-paint path (public site, sign-in, hub home) are bundled with the entry, the rest load when their
 * portal is first opened (one small request), so new pages don't grow the first load.
 */
export type PageModule = { default: ResolvedComponent };
type Registry = { pages: Record<string, () => Promise<PageModule>> };

const eager = import.meta.glob<Registry>(
    [
        '../../../modules/Site/resources/js/pages.ts',
        '../../../modules/Core/resources/js/pages.ts',
        '../../../modules/Hub/resources/js/pages.ts',
    ],
    {
        eager: true,
    },
);
const lazy = import.meta.glob<Registry>([
    '../../../modules/*/resources/js/pages.ts',
    '!../../../modules/Site/resources/js/pages.ts',
    '!../../../modules/Core/resources/js/pages.ts',
    '!../../../modules/Hub/resources/js/pages.ts',
]);

export function pagePath(name: string): string {
    const [module, ...rest] = name.split('/');
    if (!module || rest.length === 0) {
        throw new Error(`Invalid page name "${name}". Use "<Module>/<Page>", e.g. "Site/Home".`);
    }
    return `../../../modules/${module}/resources/js/Pages/${rest.join('/')}.tsx`;
}

function parts(name: string): { registry: string; page: string } {
    const [module, ...rest] = name.split('/');
    if (!module || rest.length === 0) {
        throw new Error(`Invalid page name "${name}". Use "<Module>/<Page>", e.g. "Site/Home".`);
    }
    return { registry: `../../../modules/${module}/resources/js/pages.ts`, page: `./Pages/${rest.join('/')}.tsx` };
}

export async function resolvePage(name: string): Promise<ResolvedComponent> {
    const { registry, page } = parts(name);
    const loaded = eager[registry] ?? (lazy[registry] ? await lazy[registry]() : undefined);
    const loader = loaded?.pages[page];
    if (!loader) {
        throw new Error(`Page not found: ${name} (expected ${pagePath(name)})`);
    }
    return (await loader()).default;
}
