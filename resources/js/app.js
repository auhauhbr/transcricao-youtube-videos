import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

const pages = import.meta.glob('./pages/**/*.vue');
const applicationName = import.meta.env.VITE_APP_NAME || 'Transcrev';

createInertiaApp({
    title: (title) => (title ? `${title} — ${applicationName}` : applicationName),
    resolve: async (name) => {
        const loadPage = pages[`./pages/${name}.vue`];

        if (!loadPage) {
            throw new Error(`Página Inertia não encontrada: ${name}`);
        }

        return (await loadPage()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
