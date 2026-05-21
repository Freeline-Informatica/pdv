import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { initTheme } from './composables/useTheme';
import { consumeBootstrappedSession } from './lib/auth';

initTheme();
consumeBootstrappedSession();

createApp(App).use(router).mount('#app');
