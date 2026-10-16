import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';
import '../css/layout.css';
import { initializeTheme } from './theme';
initializeTheme();
const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');
createInertiaApp({
  title: (title) => `${title} · PharmaFlow`,
  resolve: (name) => pages[`./Pages/${name}.vue`]().then((module) => module.default),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
  progress: { color: '#0d9488' },
});
