import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import basicSsl from '@vitejs/plugin-basic-ssl';

export default defineConfig(({ command }) => {
    const plugins = [
        basicSsl(),
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ];

    if (command === 'build') {
        const laravelPlugin = plugins.flat().find(p => p && p.name === 'laravel');
        if (laravelPlugin && laravelPlugin.transform) {
            delete laravelPlugin.transform;
        }
    }

    return {
        plugins,
        server: {
            cors: {
                origin: '*',
            },
        },
    };
});
