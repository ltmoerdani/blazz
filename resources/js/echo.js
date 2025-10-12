import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function getEchoInstance(broadcasterConfig = null) {
    if (!echoInstance) {
        // Default configuration
        const config = broadcasterConfig || {
            driver: 'reverb', // Default to Laravel Reverb
            key: window.broadcasterKey || 'default-app-key',
            host: window.broadcasterHost || '127.0.0.1',
            port: window.broadcasterPort || 8080,
            scheme: window.broadcasterScheme || 'http',
        };

        if (config.driver === 'pusher') {
            // Pusher configuration
            window.Pusher = Pusher;
            echoInstance = new Echo({
                broadcaster: 'pusher',
                key: config.key,
                cluster: config.cluster || 'mt1',
                encrypted: true,
                auth: {
                    headers: {
                        Authorization: `Bearer ${window.authToken || ''}`,
                    },
                },
            });
        } else {
            // Laravel Reverb configuration (default)
            echoInstance = new Echo({
                broadcaster: 'reverb',
                key: config.key,
                wsHost: config.host,
                wsPort: config.port,
                wssPort: config.port,
                enabledTransports: ['ws', 'wss'],
                auth: {
                    headers: {
                        Authorization: `Bearer ${window.authToken || ''}`,
                    },
                },
            });
        }
    }
    return echoInstance;
}

// Helper function to get broadcaster configuration from Laravel
export function getBroadcasterConfig() {
    return {
        driver: window.broadcasterDriver || 'reverb',
        key: window.broadcasterKey || 'default-app-key',
        host: window.broadcasterHost || '127.0.0.1',
        port: window.broadcasterPort || 8080,
        scheme: window.broadcasterScheme || 'http',
        cluster: window.broadcasterCluster || 'mt1',
    };
}
