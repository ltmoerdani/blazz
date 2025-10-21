/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Ensure axios sends cookies for authentication
window.axios.defaults.withCredentials = true;

// Add CSRF token to all requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Add response interceptor to handle authentication errors
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            // Handle authentication errors
            console.error('Authentication error:', error.response.data);
        } else if (error.response?.status === 419) {
            // Handle CSRF token mismatch
            console.error('CSRF token mismatch');
            // Optionally refresh the page or redirect to login
            // window.location.reload();
        }
        return Promise.reject(error);
    }
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configure Pusher for Laravel Echo
window.Pusher = Pusher;

// Initialize Echo with Reverb (Laravel's WebSocket server)
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'ohrtagckj2hqoiocg7wz',
    wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: false,
    useTLS: false,
    encrypted: false,
    disableStats: true,
    enabledTransports: ['ws'],
    auth: {
        headers: {}
    }
});
