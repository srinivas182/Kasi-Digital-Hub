import type { ResolvedComponent } from '@inertiajs/react';

/**
 * Resolves an Inertia page name such as "Site/Home" to the module page file
 * modules/Site/resources/js/Pages/Home.tsx. Each portal owns its pages inside
 * its own module folder (Portal SDK, ADR-001 / ADR-005).
 */
type PageModule = { default: ResolvedComponent };

const pages = import.meta.glob<PageModule>('../../../modules/*/resources/js/Pages/**/*.tsx');

export function pagePath(name: string): string {
    const [module, ...rest] = name.split('/');
    if (!module || rest.length === 0) {
        throw new Error(`Invalid page name "${name}". Use "<Module>/<Page>", e.g. "Site/Home".`);
    }
    return `../../../modules/${module}/resources/js/Pages/${rest.join('/')}.tsx`;
}

export async function resolvePage(name: string): Promise<ResolvedComponent> {
    const loader = pages[pagePath(name)];
    if (!loader) {
        throw new Error(`Page not found: ${name} (expected ${pagePath(name)})`);
    }
    const page = await loader();
    return page.default;
}
