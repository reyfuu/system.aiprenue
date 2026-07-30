import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { transform as lightningcss } from 'lightningcss';

// Target browser lama; angka = (major << 16). Selama ada satu target di bawah ambang
// dukungan media-query rentang (Safari 16.4 / Chrome 104 / Firefox 63), Lightning CSS
// menurunkan `@media (width>=40rem)` jadi `@media (min-width:40rem)`.
const legacyTargets = {
    safari: 12 << 16,
    ios_saf: 12 << 16,
    chrome: 87 << 16,
    firefox: 78 << 16,
    edge: 87 << 16,
};

// Tailwind v4 memancarkan media query sintaks-rentang modern (`width>=40rem`) dan plugin
// Vite-nya mengabaikan `css.lightningcss.targets`, jadi kita proses ulang CSS final di
// generateBundle. Tanpa ini semua breakpoint responsif (sm:/md:/lg:) mati di browser lama
// / Safari <16.4 / in-app browser HP — persis gejala "responsive jalan di lokal tapi tidak
// di server" saat diakses dari perangkat lawas.
function downlevelCss() {
    return {
        name: 'downlevel-css-range-mq',
        enforce: 'post',
        generateBundle(_options, bundle) {
            for (const file of Object.values(bundle)) {
                if (file.type === 'asset' && file.fileName.endsWith('.css')) {
                    const { code } = lightningcss({
                        filename: file.fileName,
                        code: Buffer.from(file.source),
                        minify: true,
                        targets: legacyTargets,
                    });
                    file.source = code.toString();
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
        downlevelCss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
