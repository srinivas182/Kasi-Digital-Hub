import type { PageModule } from '@/lib/resolvePage';

/** This portal's pages, loaded when the portal is first opened (S26: keeps the entry bundle small). */
export const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx');
