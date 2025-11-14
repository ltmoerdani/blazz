import { createApp, h, watchEffect } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import VueTelInput from 'vue-tel-input';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import './bootstrap'; // Import bootstrap.js to initialize Echo

// Function to load locale messages via API
async function loadLocaleMessages(locale) {
  const response = await axios.get(`/translations/${locale}`);
  return response.data;
}

// Function to fetch available locales from Laravel backend
async function fetchAvailableLocales() {
  const response = await axios.get('/locales');
  return response.data;
}

createInertiaApp({
  resolve: async (name) => {
    // Define paths to the components
    const pages = import.meta.glob('./Pages/**/*.vue');
    const modulePages = import.meta.glob('../../modules/**/Pages/**/*.vue');
    
    // Check if the name refers to a module component
    const [moduleName, pageName] = name.split('::');
    
    if (pageName) {
      const key = `../../modules/${moduleName}/Pages/${pageName}.vue`;
      const component = modulePages[key];
      
      if (component) {
        const resolvedComponent = await component();
        return resolvedComponent.default || resolvedComponent;
      }
    }
    
    // Otherwise, resolve from the standard Pages directory
    const component = pages[`./Pages/${name}.vue`];
    if (component) {
      const resolvedComponent = await component();
      return resolvedComponent.default || resolvedComponent;
    }
    
    throw new Error(`Page not found: ${name}`);
  },
  setup({ el, App, props, plugin }) {
    // Set global window variables from Inertia props for broadcasting
    if (props.initialPage?.props?.config) {
      const config = props.initialPage.props.config;

      // Extract config values into a map for easy access
      const configMap = {};
      config.forEach(item => {
        configMap[item.key] = item.value;
      });

      // Set Reverb configuration
      window.broadcasterDriver = configMap.broadcast_driver || 'reverb';
      window.reverbAppId = configMap.reverb_app_id;
      window.reverbAppKey = configMap.reverb_app_key;
      window.reverbHost = configMap.reverb_host || '127.0.0.1';
      window.reverbPort = configMap.reverb_port || 8080;
      window.reverbScheme = configMap.reverb_scheme || 'http';

      // Set Pusher configuration (fallback)
      window.pusherAppKey = configMap.pusher_app_key;
      window.pusherAppCluster = configMap.pusher_app_cluster;

      console.log('Broadcasting configured:', {
        driver: window.broadcasterDriver,
        reverb: {
          key: window.reverbAppKey,
          host: window.reverbHost,
          port: window.reverbPort,
          scheme: window.reverbScheme
        }
      });
    }

    // Get locale from Inertia props instead of API call
    const currentLocale = props.initialPage?.props?.currentLanguage || 'en';
    const availableLocales = props.initialPage?.props?.languages?.map(lang => lang.code) || ['en'];
    const translations = props.initialPage?.props?.translations || {};

    const i18n = createI18n({
      legacy: false,
      locale: currentLocale,
      fallbackLocale: 'en',
      messages: {
        [currentLocale]: translations
      },
    });

    const app = createApp({ render: () => h(App, props) });

    app.use(plugin)
      .use(VueApexCharts)
      .use(VueTelInput)
      .use(i18n)
      .mount(el);

    // Preload additional locale messages if needed
    if (availableLocales.includes(currentLocale) && Object.keys(translations).length === 0) {
      loadLocaleMessages(currentLocale).then(messages => {
        i18n.global.setLocaleMessage(currentLocale, messages);
      });
    }

    // Watch for locale changes and dynamically load new locale messages
    watchEffect(async () => {
      const newLocale = i18n.global.locale.value;
      if (!i18n.global.availableLocales.includes(newLocale) && availableLocales.includes(newLocale)) {
        const messages = await loadLocaleMessages(newLocale);
        i18n.global.setLocaleMessage(newLocale, messages);
      }
    });
  },
  progress: {
    delay: 250,
    color: '#198754',
    includeCSS: true,
    showSpinner: false,
  },
});
