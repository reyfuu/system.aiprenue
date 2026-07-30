import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';

export default [
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    prettier,
    {
        files: ['resources/js/**/*.vue', 'resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                route: 'readonly',
                axios: 'readonly',
                FormData: 'readonly',
                alert: 'readonly',
                confirm: 'readonly',
                prompt: 'readonly',
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                URLSearchParams: 'readonly',
                File: 'readonly',
                localStorage: 'readonly',
                setTimeout: 'readonly',
                fetch: 'readonly',
                ResizeObserver: 'readonly',
                navigator: 'readonly',
                Notification: 'readonly',
            },
        },
        rules: {
            'no-unused-vars': 'warn',
            'no-console': 'warn',
            'no-undef': 'error',
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'off',
            'vue/require-default-prop': 'off',
            'vue/require-prop-types': 'off',
            'vue/no-v-text-v-html-on-component': 'off',
            'vue/max-attributes-per-line': ['warn', { singleline: 5, multiline: 1 }],
        },
    },
    {
        ignores: [
            'public/build/**',
            'vendor/**',
            'node_modules/**',
            'storage/**',
            'mcp-server/node_modules/**',
        ],
    },
];
