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
    <!-- QR Modal -->
    <Modal :label="'WhatsApp QR Code'" :isOpen="isQRModalOpen">
      <div class="p-2 md:p-4">
        <div v-if="errorMessage" class="mb-3 p-2 text-sm rounded bg-red-50 text-red-700 border border-red-200">
          {{ errorMessage }}
        </div>
        <div class="flex justify-center mb-4 min-h-[16rem] items-center">
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
              <p class="text-gray-500">Preparing QR code...</p>
            </div>
          </div>
        </div>
        <div class="text-center">
          <!-- QR Expiry Timer -->
          <div v-if="qrCode" class="text-center text-sm text-gray-600">
            QR code expires in: <span class="font-bold text-red-600">{{ expiryCountdown }}</span> seconds
          </div>
          <!-- Actions -->
          <div class="flex items-center justify-center gap-3 mt-4">
            <button 
              @click="refreshQRCode" 
              :disabled="isRefreshing"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isRefreshing ? 'Refreshing...' : 'Refresh QR Code' }}
            </button>
            <button 
              type="button" 
              @click="isQRModalOpen = false"
              class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-600 hover:bg-slate-200"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Modal>
    <!-- ...existing WhatsAppSetup.vue template code... -->
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
    <!-- ...existing WhatsAppSetup.vue template code... -->
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
        v-if="$page.props.app?.debug"
        @click="debugInfo = !debugInfo"
        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm"
      >
        Debug
      </button>
    </div>
    <!-- ...existing WhatsAppSetup.vue template code... -->
    <!-- Debug Information (development only) -->
  <div v-if="$page.props.app?.debug && debugInfo" class="mt-6 p-4 bg-gray-100 rounded-lg">
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { getEchoInstance } from '@/echo';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

export default {
  name: 'WhatsappSessions',
  components: {
    SettingLayout,
    Modal,
  },
  setup() {
    const page = usePage();
    const workspaceId = page.props.workspace?.id || 1;
    const status = ref('disconnected');
  const qrCode = ref(null);
  const errorMessage = ref('');
    const sessionId = ref(null);
    const connectedPhoneNumber = ref(null);
    const isQRModalOpen = ref(false);

    // Fallback: Modal QR tetap terbuka selama status connecting/qr_required
    watch(
      () => status.value,
      (newStatus) => {
        if (newStatus === 'connecting' || newStatus === 'qr_required') {
          isQRModalOpen.value = true;
        } else if (newStatus === 'connected' || newStatus === 'disconnected') {
          isQRModalOpen.value = false;
        }
      }
    );
    const isConnecting = ref(false);
    const isDisconnecting = ref(false);
    const isRefreshing = ref(false);
    const expiryTime = ref(null);
    const expiryCountdown = ref(300);
    const debugInfo = ref(false);
    let expiryInterval = null;
    let echoChannel = null;
    const pusherSettings = page.props.pusherSettings || {};
    const reverbSettings = page.props.reverbSettings || {};
    const broadcastDriver = page.props.broadcastDriver || 'reverb';
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
    const initiateConnection = async () => {
  // Optimistically show modal and set connecting status for better UX
      status.value = 'connecting';
      isQRModalOpen.value = true;
  errorMessage.value = '';
      isConnecting.value = true;
      try {
        const response = await axios.post('/api/whatsapp-webjs/sessions/create', { workspace_id: workspaceId });
        if (response.data.success) {
          // Open modal immediately; show spinner until QR arrives via websocket
          isQRModalOpen.value = true;
          // If session already exists, we'll wait for status/QR event
          if (response.data.data?.already_exists) {
            status.value = 'connecting';
          } else {
            status.value = 'qr_required';
            sessionId.value = response.data.data?.session_id || null;
          }
        } else {
          throw new Error(response.data.error || 'Unknown error');
        }
      } catch (error) {
        console.error('initiateConnection failed', error);
        const errMsg = (error.response?.data?.error) || (error.message) || 'Failed to initiate connection';
        if (errMsg.includes('Session already exists')) {
          // Try to fetch cached QR immediately
          await tryFetchCachedQr();
          // If QR still not available, show error
          if (!qrCode.value) {
            errorMessage.value = 'Session already exists, but QR code not available.';
          } else {
            errorMessage.value = '';
          }
          isQRModalOpen.value = true;
        } else {
          errorMessage.value = errMsg;
        }
      } finally {
        isConnecting.value = false;
      }
    };
    const disconnectSession = async () => {
      if (!confirm('Are you sure you want to disconnect WhatsApp?')) return;
      isDisconnecting.value = true;
      try {
        const response = await axios.post('/api/whatsapp-webjs/sessions/disconnect', { workspace_id: workspaceId });
        if (response.data.success) {
          status.value = 'disconnected';
          qrCode.value = null;
          sessionId.value = null;
          connectedPhoneNumber.value = null;
          isQRModalOpen.value = false;
        } else {
          throw new Error(response.data.error || 'Unknown error');
        }
      } catch (error) {
        console.error('disconnectSession failed', error);
      } finally {
        isDisconnecting.value = false;
      }
    };
    const refreshQRCode = async () => {
      isRefreshing.value = true;
      try {
        const response = await axios.post('/api/whatsapp-webjs/sessions/refresh-qr', { workspace_id: workspaceId });
        if (response.data.success) {
          resetExpiryTimer();
        } else {
          throw new Error(response.data.error || 'Unknown error');
        }
      } catch (error) {
        console.error('refreshQRCode failed', error);
      } finally {
        isRefreshing.value = false;
      }
    };
    const startExpiryTimer = () => {
      expiryTime.value = Date.now() + (300 * 1000);
      expiryCountdown.value = 300;
      if (expiryInterval) clearInterval(expiryInterval);
      expiryInterval = setInterval(() => {
        const remaining = Math.floor((expiryTime.value - Date.now()) / 1000);
        expiryCountdown.value = Math.max(0, remaining);
        if (remaining <= 0) {
          clearInterval(expiryInterval);
          qrCode.value = null;
        }
      }, 1000);
    };
    const resetExpiryTimer = () => {
      if (expiryInterval) clearInterval(expiryInterval);
      startExpiryTimer();
    };
    const setupEchoListeners = () => {
      try {
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
        const channelName = `whatsapp.${workspaceId}`;
        echoChannel = echo.channel(channelName)
          .listen('.whatsapp.qr.generated', (event) => {
            console.log('Echo: whatsapp.qr.generated received', event);
            errorMessage.value = '';
            // Backend broadcasts payload as { qr, session_id }
            qrCode.value = event.qr || event.qr_code || null;
            sessionId.value = event.session_id;
            status.value = 'qr_required';
            startExpiryTimer();
            // Ensure modal is open when QR arrives
            isQRModalOpen.value = true;
          })
          .listen('.whatsapp.session.status', (event) => {
            console.log('Echo: whatsapp.session.status received', event);
            errorMessage.value = '';
            status.value = event.status;
            sessionId.value = event.session_id;
            if (event.status === 'connected') {
              connectedPhoneNumber.value = event.phone_number || null;
              qrCode.value = null;
              if (expiryInterval) clearInterval(expiryInterval);
              // Auto-close modal on success per requirement
              isQRModalOpen.value = false;
            } else if (event.status === 'disconnected') {
              connectedPhoneNumber.value = null;
              qrCode.value = null;
              sessionId.value = null;
              if (expiryInterval) clearInterval(expiryInterval);
              isQRModalOpen.value = false;
            }
          });
      } catch (error) {
        console.error('setupEchoListeners failed', error);
      }
    };
    const loadCurrentStatus = async () => {
      try {
        const response = await axios.get(`/api/whatsapp-webjs/sessions/status/${workspaceId}`);
        if (response.data.success) {
          status.value = response.data.status || 'disconnected';
          connectedPhoneNumber.value = response.data.phone_number;
          sessionId.value = response.data.session_id;
        }
      } catch (error) {
        console.error('loadCurrentStatus failed', error);
      }
    };
    const tryFetchCachedQr = async () => {
      try {
        const res = await axios.get('/api/whatsapp-webjs/sessions/last-qr');
        if (res.data?.success && res.data.data?.qr) {
          qrCode.value = res.data.data.qr;
          sessionId.value = res.data.data.session_id;
          status.value = 'qr_required';
          isQRModalOpen.value = true;
          startExpiryTimer();
        }
      } catch (e) {
        // silent
      }
    };
    onMounted(() => {
      loadCurrentStatus();
      setupEchoListeners();
      // As a fallback, try to fetch cached QR shortly after mounting
      setTimeout(() => {
        if (!qrCode.value && (status.value === 'connecting' || status.value === 'qr_required')) {
          tryFetchCachedQr();
        }
      }, 1500);
    });
    onUnmounted(() => {
      if (expiryInterval) clearInterval(expiryInterval);
      if (echoChannel) {
        try {
          echoChannel.stopListening('.whatsapp.qr.generated');
          echoChannel.stopListening('.whatsapp.session.status');
        } catch (error) {
          console.error('cleanup listeners failed', error);
        }
      }
    });
    return {
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
      isQRModalOpen,
      statusText,
      statusClass,
      initiateConnection,
      disconnectSession,
      refreshQRCode,
    };
  },
};
</script>

<style scoped>
/* ...full WhatsAppSetup.vue styles... */
</style>
