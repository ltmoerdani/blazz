<template>
    <SettingLayout>
    <div class="whatsapp-setup-container">
        <!-- Setup Instructions -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold mb-2">Connect WhatsApp Web JS</h2>
            <p class="text-gray-600 mb-4">Scan QR code dengan WhatsApp mobile app untuk connect.</p>
            
            <!-- Provider Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm">
                        <p class="font-medium text-blue-800">WhatsApp Web JS Integration</p>
                        <p class="text-blue-600">Free alternative to Meta API. No monthly fees, unlimited messages.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- QR Code Display -->
        <div class="qr-code-section bg-white border rounded-lg p-6 mb-4" v-if="status === 'qr_required'">
            <div class="flex justify-center mb-4">
                <div v-if="qrCode" class="text-center">
                    <img :src="qrCode" alt="WhatsApp QR Code" class="w-64 h-64 mx-auto border rounded-lg" />
                    <p class="text-sm text-gray-600 mt-2">Scan this QR code with your WhatsApp mobile app</p>
                </div>
                <div v-else class="w-64 h-64 flex items-center justify-center bg-gray-100 border rounded-lg">
                    <div class="text-center">
                        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-500">Generating QR code...</p>
                    </div>
                </div>
            </div>
            
            <!-- QR Expiry Timer -->
            <div v-if="qrCode" class="text-center text-sm text-gray-600">
                QR code expires in: <span class="font-bold text-red-600">{{ expiryCountdown }}</span> seconds
            </div>
            
            <!-- Refresh Button -->
            <div class="text-center mt-4">
                <button 
                    @click="refreshQRCode" 
                    :disabled="isRefreshing"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ isRefreshing ? 'Refreshing...' : 'Refresh QR Code' }}
                </button>
            </div>
        </div>
        
        <!-- Connection Status -->
        <div class="status-section p-4 rounded-lg" :class="statusClass">
            <div class="flex items-center">
                <div class="status-icon mr-3">
                    <svg v-if="status === 'connected'" class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <svg v-else-if="status === 'connecting' || status === 'qr_required'" class="w-6 h-6 text-yellow-500 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold">{{ statusText }}</p>
                    <p v-if="connectedPhoneNumber" class="text-sm text-gray-600">
                        Connected: {{ connectedPhoneNumber }}
                    </p>
                    <p v-if="sessionId" class="text-xs text-gray-500">
                        Session: {{ sessionId }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="actions mt-6 flex gap-4">
            <button 
                v-if="status === 'disconnected' || !status"
                @click="initiateConnection" 
                :disabled="isConnecting"
                class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
            >
                <svg v-if="isConnecting" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ isConnecting ? 'Connecting...' : 'Connect WhatsApp' }}
            </button>
            
            <button 
                v-if="status === 'connected'"
                @click="disconnectSession" 
                :disabled="isDisconnecting"
                class="px-6 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
            >
                <svg v-if="isDisconnecting" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ isDisconnecting ? 'Disconnecting...' : 'Disconnect' }}
            </button>
            
            <!-- Debug Info (development only) -->
            <button 
                v-if="$page.props.app.debug"
                @click="debugInfo = !debugInfo"
                class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm"
            >
                Debug
            </button>
        </div>
        
        <!-- Debug Information (development only) -->
        <div v-if="$page.props.app.debug && debugInfo" class="mt-6 p-4 bg-gray-100 rounded-lg">
            <h3 class="font-bold mb-2">Debug Information</h3>
            <pre class="text-xs bg-white p-2 rounded overflow-x-auto">{{ JSON.stringify({
                status: status,
                workspaceId: workspaceId,
                sessionId: sessionId,
                connectedPhoneNumber: connectedPhoneNumber,
                qrCodePresent: !!qrCode,
                broadcastDriver: broadcastDriver,
                echoConnected: !!echoChannel,
            }, null, 2) }}</pre>
        </div>
    </div>
    </SettingLayout>
</template>

<script>
import SettingLayout from './Layout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { getEchoInstance } from '@/echo';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

export default {
    name: 'WhatsAppSetup',
    components: {
        SettingLayout,
    },
    
    setup() {
        const page = usePage();
        const workspaceId = page.props.workspace?.id || 1; // Fallback to workspace 1 for testing
        
        // Reactive state
        const status = ref('disconnected'); // 'disconnected', 'qr_required', 'connecting', 'connected'
        const qrCode = ref(null);
        const sessionId = ref(null);
        const connectedPhoneNumber = ref(null);
        const isConnecting = ref(false);
        const isDisconnecting = ref(false);
        const isRefreshing = ref(false);
        const expiryTime = ref(null);
        const expiryCountdown = ref(300); // 5 minutes = 300 seconds
        const debugInfo = ref(false);
        
        let expiryInterval = null;
        let echoChannel = null;
        
        // Get broadcast configuration from page props
        const pusherSettings = page.props.pusherSettings || {};
        const reverbSettings = page.props.reverbSettings || {};
        const broadcastDriver = page.props.broadcastDriver || 'reverb'; // Default to reverb for Web JS
        
        // Computed properties
        const statusText = computed(() => {
            switch (status.value) {
                case 'connected': return 'Connected to WhatsApp';
                case 'connecting': return 'Connecting to WhatsApp...';
                case 'qr_required': return 'Scan QR Code to Connect';
                case 'disconnected': return 'Not Connected';
                default: return 'Unknown Status';
            }
        });
        
        const statusClass = computed(() => {
            switch (status.value) {
                case 'connected': return 'bg-green-50 border border-green-200';
                case 'connecting': return 'bg-yellow-50 border border-yellow-200';
                case 'qr_required': return 'bg-blue-50 border border-blue-200';
                default: return 'bg-gray-50 border border-gray-200';
            }
        });
        
        // Methods
        const initiateConnection = async () => {
            isConnecting.value = true;
            
            try {
                console.log('Initiating WhatsApp connection...');
                const response = await axios.post('/api/whatsapp-webjs/sessions/create', {
                    workspace_id: workspaceId,
                });
                
                if (response.data.success) {
                    status.value = 'qr_required';
                    sessionId.value = response.data.data?.session_id || null;
                    console.log('Session creation successful:', response.data);
                    // QR code will be received via WebSocket event
                } else {
                    throw new Error(response.data.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Failed to initiate connection:', error);
                alert('Failed to start connection: ' + (error.response?.data?.error || error.message));
                status.value = 'disconnected';
            } finally {
                isConnecting.value = false;
            }
        };
        
        const disconnectSession = async () => {
            if (!confirm('Are you sure you want to disconnect WhatsApp?')) {
                return;
            }
            
            isDisconnecting.value = true;
            
            try {
                console.log('Disconnecting WhatsApp session...');
                const response = await axios.post('/api/whatsapp-webjs/sessions/disconnect', {
                    workspace_id: workspaceId,
                });
                
                if (response.data.success) {
                    status.value = 'disconnected';
                    qrCode.value = null;
                    sessionId.value = null;
                    connectedPhoneNumber.value = null;
                    console.log('Disconnection successful');
                } else {
                    throw new Error(response.data.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Failed to disconnect:', error);
                alert('Failed to disconnect: ' + (error.response?.data?.error || error.message));
            } finally {
                isDisconnecting.value = false;
            }
        };
        
        const refreshQRCode = async () => {
            isRefreshing.value = true;
            
            try {
                console.log('Refreshing QR code...');
                // Request new QR code dari backend
                const response = await axios.post('/api/whatsapp-webjs/sessions/refresh-qr', {
                    workspace_id: workspaceId,
                });
                
                if (response.data.success) {
                    // QR will be updated via WebSocket event
                    resetExpiryTimer();
                    console.log('QR refresh successful');
                } else {
                    throw new Error(response.data.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Failed to refresh QR code:', error);
                alert('Failed to refresh QR code: ' + (error.response?.data?.error || error.message));
            } finally {
                isRefreshing.value = false;
            }
        };
        
        const startExpiryTimer = () => {
            expiryTime.value = Date.now() + (300 * 1000); // 5 minutes from now
            expiryCountdown.value = 300;
            
            if (expiryInterval) {
                clearInterval(expiryInterval);
            }
            
            expiryInterval = setInterval(() => {
                const remaining = Math.floor((expiryTime.value - Date.now()) / 1000);
                expiryCountdown.value = Math.max(0, remaining);
                
                if (remaining <= 0) {
                    clearInterval(expiryInterval);
                    qrCode.value = null;
                    console.log('QR code expired');
                    // Optionally auto-refresh QR code
                    // refreshQRCode();
                }
            }, 1000);
        };
        
        const resetExpiryTimer = () => {
            if (expiryInterval) {
                clearInterval(expiryInterval);
            }
            startExpiryTimer();
        };
        
        const setupEchoListeners = () => {
            try {
                console.log('Setting up Echo listeners...', {
                    workspaceId,
                    broadcastDriver,
                    pusherSettings,
                    reverbSettings
                });
                
                // Initialize Echo instance with dynamic broadcaster
                const echo = getEchoInstance(
                    pusherSettings.pusher_app_key || 'default-pusher-key',
                    pusherSettings.pusher_app_cluster || 'mt1',
                    broadcastDriver,
                    {
                        key: reverbSettings.reverb_app_key || 'default-reverb-key',
                        host: reverbSettings.reverb_host || '127.0.0.1',
                        port: reverbSettings.reverb_port || 8080,
                        scheme: reverbSettings.reverb_scheme || 'http'
                    }
                );
                
                // Subscribe to workspace-specific WhatsApp channel
                const channelName = `whatsapp.${workspaceId}`;
                console.log(`Subscribing to channel: ${channelName}`);
                
                echoChannel = echo.channel(channelName)
                    .listen('WhatsAppQRGenerated', (event) => {
                        console.log('QR Code generated event received:', event);
                        qrCode.value = event.qr_code;
                        sessionId.value = event.session_id;
                        status.value = 'qr_required';
                        startExpiryTimer();
                    })
                    .listen('WhatsAppSessionStatusChanged', (event) => {
                        console.log('Session status changed event received:', event);
                        status.value = event.status;
                        sessionId.value = event.session_id;
                        
                        if (event.status === 'connected') {
                            connectedPhoneNumber.value = event.phone_number;
                            qrCode.value = null;
                            if (expiryInterval) {
                                clearInterval(expiryInterval);
                            }
                        } else if (event.status === 'disconnected') {
                            connectedPhoneNumber.value = null;
                            qrCode.value = null;
                            sessionId.value = null;
                            if (expiryInterval) {
                                clearInterval(expiryInterval);
                            }
                        }
                    });
                    
                console.log('Echo listeners setup complete');
                
            } catch (error) {
                console.error('Failed to setup Echo listeners:', error);
            }
        };
        
        const loadCurrentStatus = async () => {
            try {
                console.log(`Loading current session status for workspace ${workspaceId}...`);
                const response = await axios.get(`/api/whatsapp-webjs/sessions/status/${workspaceId}`);
                
                if (response.data.success) {
                    status.value = response.data.status || 'disconnected';
                    connectedPhoneNumber.value = response.data.phone_number;
                    sessionId.value = response.data.session_id;
                    console.log('Current status loaded:', response.data);
                } else {
                    console.warn('Failed to load status:', response.data);
                }
            } catch (error) {
                console.error('Failed to load session status:', error);
                // Don't show alert for status loading failure
            }
        };
        
        // Lifecycle hooks
        onMounted(() => {
            console.log('WhatsAppSetup component mounted');
            loadCurrentStatus();
            setupEchoListeners();
        });
        
        onUnmounted(() => {
            console.log('WhatsAppSetup component unmounting...');
            
            if (expiryInterval) {
                clearInterval(expiryInterval);
            }
            
            if (echoChannel) {
                try {
                    echoChannel.stopListening('WhatsAppQRGenerated');
                    echoChannel.stopListening('WhatsAppSessionStatusChanged');
                    console.log('Echo listeners cleaned up');
                } catch (error) {
                    console.error('Error cleaning up Echo listeners:', error);
                }
            }
        });
        
        return {
            // Data
            workspaceId,
            status,
            qrCode,
            sessionId,
            connectedPhoneNumber,
            isConnecting,
            isDisconnecting,
            isRefreshing,
            expiryCountdown,
            debugInfo,
            broadcastDriver,
            echoChannel,
            
            // Computed
            statusText,
            statusClass,
            
            // Methods
            initiateConnection,
            disconnectSession,
            refreshQRCode,
        };
    },
};
</script>

<style scoped>
.whatsapp-setup-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 1rem;
}

/* QR Code animation */
.qr-code-section img {
    transition: opacity 0.3s ease-in-out;
}

.qr-code-section img:hover {
    opacity: 0.8;
}

/* Status transitions */
.status-section {
    transition: all 0.3s ease-in-out;
}

/* Loading animations are handled by Tailwind classes */
</style>