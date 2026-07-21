<?php

return [
    'ssr' => [
        // Default SENGAJA false (bawaan paket: true). Produksi = shared hosting
        // yang tidak menjalankan Node, jadi SSR tak akan pernah bisa hidup di sana.
        // Selama bundle SSR belum pernah dibuat, `enabled => true` memang tak
        // berefek — tapi begitu ada yang menjalankan build ber-SSR, produksi mulai
        // gagal render tanpa sebab yang jelas. Matikan di sumbernya.
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),
        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),
    ],

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

    'expose_shared_prop_keys' => true,

    'history' => [
        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),
    ],
];
