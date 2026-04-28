import { defineConfig, loadEnv } from 'vite';
import { fileURLToPath } from 'node:url'
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import svgLoader from "vite-svg-loader";
import { quasar, transformAssetUrls } from '@quasar/vite-plugin'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // Extrai apenas o pathname quando VITE_BASE_PATH contém uma URL completa (ex: https://host/path).
    // Isso acontece quando VITE_BASE_PATH=${APP_URL} no .env.
    // O buildDirectory ('build') é incluído no base para que as URLs de assets geradas dentro
    // do CSS (fontes, imagens) também tenham o prefixo correto (ex: /homolog/app/build/assets/x.woff2).
    const rawBase = env.VITE_BASE_PATH || '/';
    let basePath;
    try {
        basePath = new URL(rawBase).pathname.replace(/([^/])$/, '$1/');
    } catch {
        basePath = rawBase.replace(/([^/])$/, '$1/');
    }
    const base = basePath === '/' ? '/' : `${basePath}build/`;

    return {
        base,
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls,
                },
            }),
            svgLoader(),
            quasar({
                autoImportComponentCase: "combined",
                sassVariables: fileURLToPath(new URL('resources/css/quasar-variables.sass', import.meta.url)),
            }),
        ],
    };
});
