import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

/**
 * Enhanced Echo instance with dynamic broadcaster support
 * Supports both Pusher and Laravel Reverb (via Pusher protocol compatibility)
 * 
 * @param {string} pusherKey - Pusher app key (fallback for Pusher broadcaster)
 * @param {string} pusherCluster - Pusher cluster (fallback for Pusher broadcaster)
 * @param {string} broadcasterType - 'reverb' or 'pusher' (default: 'pusher')
 * @param {object} reverbConfig - Reverb configuration object {key, host, port, scheme}
 */
export function getEchoInstance(pusherKey, pusherCluster, broadcasterType = 'pusher', reverbConfig = {}) {
    if (!echoInstance) {
        window.Pusher = Pusher;
        
        let config;
        
        if (broadcasterType === 'reverb') {
            // Laravel Reverb configuration (uses Pusher protocol compatibility)
            config = {
                broadcaster: 'pusher',  // Reverb uses Pusher protocol compatibility
                key: reverbConfig.key || 'default-reverb-key',
                // pusher-js requires a cluster option even when using wsHost/wsPort
                cluster: 'mt1',
                wsHost: reverbConfig.host || '127.0.0.1',
                wsPort: reverbConfig.port || 8080,
                wssPort: reverbConfig.port || 8080,
                forceTLS: (reverbConfig.scheme || 'http') === 'https',
                encrypted: true,
                disableStats: true,
                enabledTransports: ['ws', 'wss'],
            };
            
            console.log('Echo: Initializing with Laravel Reverb broadcaster', config);
        } else {
            // Traditional Pusher configuration (backward compatible)
            config = {
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: pusherCluster,
                encrypted: true,
            };
            
            console.log('Echo: Initializing with Pusher broadcaster', config);
        }
        
        try {
            echoInstance = new Echo(config);
            console.log(`Echo: Successfully initialized with ${broadcasterType} broadcaster`);
        } catch (error) {
            console.error(`Echo: Failed to initialize with ${broadcasterType} broadcaster:`, error);
            
            // Fallback to Pusher if Reverb fails
            if (broadcasterType === 'reverb') {
                console.warn('Echo: Falling back to Pusher broadcaster...');
                if (pusherKey) {
                    echoInstance = new Echo({
                        broadcaster: 'pusher',
                        key: pusherKey,
                        cluster: pusherCluster || 'mt1',
                        encrypted: true,
                    });
                    console.log('Echo: Fallback to Pusher successful');
                } else {
                    console.warn('Echo: Pusher key missing. Using no-op Echo to prevent runtime errors.');
                    const noop = {
                        channel: () => ({ listen: () => noop, stopListening: () => noop }),
                        private: () => ({ listen: () => noop, stopListening: () => noop }),
                        leave: () => {},
                        disconnect: () => {},
                    };
                    echoInstance = noop;
                }
            } else {
                throw error;
            }
        }
    }
    
    return echoInstance;
}

/**
 * Legacy compatibility function - maintains backward compatibility
 * Automatically detects if enhanced parameters are provided
 */
export function getLegacyEchoInstance(pusherKey, pusherCluster) {
    return getEchoInstance(pusherKey, pusherCluster, 'pusher', {});
}