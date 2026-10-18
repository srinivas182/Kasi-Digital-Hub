import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'storage', 'test-results', 'playwright-report'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['scripts/**/*.mjs', '*.config.{js,ts}'],
        languageOptions: { globals: { ...globals.node } },
    },
    {
        files: ['resources/js/**/*.{ts,tsx}', 'modules/*/resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            globals: { ...globals.browser, ...globals.node },
        },
        plugins: { 'react-hooks': reactHooks },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'no-console': ['error', { allow: ['warn', 'error'] }],
            '@typescript-eslint/consistent-type-imports': 'error',
        },
    },
    {
        // S22: k6 load-test scripts run in k6, which provides these globals.
        files: ['loadtest/**/*.js'],
        languageOptions: { globals: { __ENV: 'readonly', __VU: 'readonly', __ITER: 'readonly' } },
    },
);
