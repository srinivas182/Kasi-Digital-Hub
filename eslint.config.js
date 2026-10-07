import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'storage'] },
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
);
