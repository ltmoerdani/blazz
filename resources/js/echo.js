import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function getEchoInstance(broadcasterConfig = null, cluster = null) {
    // ✅ REALTIME FIX: Use global window.Echo instance from bootstrap.js
    // This prevents multiple Echo instances and ensures consistent WebSocket connection
    if (window.Echo) {
        console.log('✅ Using global Echo instance from bootstrap.js');
        return window.Echo;
    }

    if (!echoInstance) {
        // Get configuration from Laravel (via Inertia middleware)
        const config = broadcasterConfig || {
            driver: window.broadcasterDriver || 'reverb',
            key: window.broadcasterKey || window.reverbAppKey || 'ohrtagckj2hqoiocg7wz',
            host: window.broadcasterHost || window.reverbHost || '127.0.0.1',
            port: window.broadcasterPort || window.reverbPort || 8080,
            scheme: window.broadcasterScheme || window.reverbScheme || 'http',
            cluster: cluster || window.broadcasterCluster || 'mt1',
        };
        
        console.log('🔧 Echo Configuration:', config);

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
            // CRITICAL: Set Pusher globally and configure it BEFORE Echo initialization
            window.Pusher = Pusher;
            
            const echoConfig = {
                broadcaster: 'reverb',
                key: config.key,
                wsHost: config.host,
                wsPort: config.port,
                wssPort: config.port,
                forceTLS: false,
                useTLS: false,
                encrypted: false,
                disableStats: true,
                enabledTransports: ['ws'],
                auth: {
                    headers: {
                        Authorization: `Bearer ${window.authToken || ''}`,
                    },
                },
            };
            
            console.log('🚀 Initializing Echo with Reverb:', echoConfig);
            echoInstance = new Echo(echoConfig);
            console.log('✅ Echo instance created successfully');
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
