/**
 * Vitest configuration, kept separate from vite.config.ts on purpose: the
 * Laravel Vite plugin refuses to start in CI environments (it guards against
 * running the HMR server there), so tests must not load it.
 */
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@modules': fileURLToPath(new URL('./modules', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/__tests__/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}', 'modules/*/resources/js/**/*.test.{ts,tsx}'],
        env: { TZ: 'UTC' },
    },
});
