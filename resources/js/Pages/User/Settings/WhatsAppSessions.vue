<template>
    <SettingLayout :modules="props.modules">
        <div class="md:h-[90vh]">
            <div class="flex justify-center items-center mb-8">
                <div class="md:w-[60em]">
                    <div v-if="!sessions || sessions.length === 0" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="flex items-center px-4 pt-2 pb-4">
                            <div class="w-[70%]">
                                <h2 class="text-[17px]">{{ $t('Setup WhatsApp Numbers') }}</h2>
                                <span class="flex items-center mt-1">
                                    {{ $t('Setup your WhatsApp numbers to be able to receive and send messages via WhatsApp Web.JS.') }}
                                </span>
                            </div>
                            <div class="ml-auto">
                                <button @click="addSession" class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit">
                                    {{ $t('Add WhatsApp Number') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4C7.58 4 4 7.58 4 12s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6zm3-7h-2v-2c0-.55-.45-1-1-1s-1 .45-1 1v2H9c-.55 0-1 .45-1 1s.45 1 1 1h2v2c0 .55.45 1 1 1s1-.45 1-1v-2h2c.55 0 1-.45 1-1s-.45-1-1-1z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="sessions && sessions.length > 0" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="grid grid-cols-4 items-center px-4 gap-x-4 py-2 border-b relative">
                            <div class="border-r">
                                <div>{{ $t('Total Numbers') }}</div>
                                <div>{{ sessions.length }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Connected') }}</div>
                                <div>{{ sessions.filter(s => s.status === 'connected').length }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Primary Number') }}</div>
                                <div>{{ sessions.find(s => s.is_primary)?.formatted_phone_number || 'None' }}</div>
                            </div>
                            <div>
                                <div>{{ $t('Status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">
                                    {{ sessions.some(s => s.status === 'connected') ? 'Active' : 'Inactive' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Banner -->
                    <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">
                                    {{ $t('WhatsApp Numbers (On-premise Multi-Session)') }}
                                </h3>
                                <div class="mt-2 text-sm text-green-700">
                                    <p>
                                        {{ $t('Connect multiple personal/business WhatsApp numbers via QR code. No Meta approval required. Runs on your server.') }}
                                    </p>
                                    <p class="mt-2">
                                        {{ $t('Need official WhatsApp Business API (Cloud)?') }}
                                        <a href="/settings/whatsapp" class="font-medium underline hover:text-green-900">
                                            {{ $t('Go to Meta API Settings →') }}
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sessions List -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-md">
                        <div v-if="sessions.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ $t('No WhatsApp numbers connected') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $t('Get started by adding your first WhatsApp number.') }}</p>
                            <div class="mt-6">
                                <button
                                    @click="addSession"
                                    :disabled="!canAddSession"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 4C7.58 4 4 7.58 4 12s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6zm3-7h-2v-2c0-.55-.45-1-1-1s-1 .45-1 1v2H9c-.55 0-1 .45-1 1s.45 1 1 1h2v2c0 .55.45 1 1 1s1-.45 1-1v-2h2c.55 0 1-.45 1-1s-.45-1-1-1z"/>
                                    </svg>
                                    {{ $t('Add WhatsApp Number') }}
                                </button>
                            </div>
                        </div>

                        <ul v-else class="divide-y divide-gray-200">
                            <li v-for="session in sessionsList" :key="session.uuid" class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="flex items-center">
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ session.formatted_phone_number || session.phone_number || 'Unknown Number' }}
                                                </p>
                                                <span v-if="session.is_primary" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $t('Primary') }}
                                                </span>
                                            </div>
                                            <div class="flex items-center mt-1">
                                                <span :class="[
                                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                    session.status === 'connected' ? 'bg-green-100 text-green-800' :
                                                    session.status === 'qr_scanning' ? 'bg-yellow-100 text-yellow-800' :
                                                    session.status === 'disconnected' ? 'bg-red-100 text-red-800' :
                                                    'bg-gray-100 text-gray-800'
                                                ]">
                                                    {{ $t(session.status) }}
                                                </span>
                                                <span class="ml-2 text-sm text-gray-500">
                                                    {{ $t('Health Score') }}: {{ session.health_score }}%
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-500 mt-1">
                                                {{ $t('Added') }} {{ formatDate(session.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button
                                            v-if="!session.is_primary"
                                            @click="setPrimary(session.uuid)"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            {{ $t('Set Primary') }}
                                        </button>
                                        <button
                                            v-if="session.status === 'disconnected'"
                                            @click="reconnect(session.uuid)"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            {{ $t('Reconnect') }}
                                        </button>
                                        <button
                                            @click="disconnect(session.uuid)"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            {{ $t('Disconnect') }}
                                        </button>
                                        <button
                                            @click="deleteSession(session.uuid)"
                                            class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-sm font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            {{ $t('Delete') }}
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Session Modal -->
        <div v-if="showAddModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ $t('Add WhatsApp Number') }}</h3>
                        <button @click="closeAddModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div v-if="qrCode" class="text-center">
                        <div class="mb-4">
                            <img :src="qrCode" alt="QR Code" class="mx-auto border border-gray-300 rounded" />
                        </div>
                        <div class="mb-4">
                            <div class="text-sm text-gray-600 mb-2">{{ $t('Scan this QR code with WhatsApp') }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $t('Expires in') }}: <span class="font-mono">{{ formatTime(countdown) }}</span>
                            </div>
                        </div>
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4">
                            <div class="flex">
                                <div class="text-sm text-blue-700">
                                    <p>{{ $t('1. Open WhatsApp on your phone') }}</p>
                                    <p>{{ $t('2. Tap Menu → Linked Devices') }}</p>
                                    <p>{{ $t('3. Tap Link a Device') }}</p>
                                    <p>{{ $t('4. Scan this QR code') }}</p>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="regenerateQR"
                            class="w-full inline-flex justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            {{ $t('Regenerate QR Code') }}
                        </button>
                    </div>

                    <div v-else class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="mt-2 text-sm text-gray-600">{{ $t('Generating QR code...') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>

<script setup>
import SettingLayout from "./Layout.vue";
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3';
import axios from 'axios'
import { getEchoInstance } from '../../../echo'

const props = defineProps({
    sessions: Array,
    canAddSession: Boolean,
    modules: Array,
    embeddedSignupActive: Boolean,
    graphAPIVersion: String,
    appId: String,
    configId: String,
    settings: Object,
    title: String,
    workspaceId: Number,
})

const workspace = computed(() => usePage().props.workspace);

// Reactive sessions list (instead of using props directly)
const sessionsList = ref([...props.sessions])

const showAddModal = ref(false)
const qrCode = ref(null)
const countdown = ref(300) // 5 minutes
const currentSessionId = ref(null)
const qrTimeout = ref(null)
let countdownInterval = null
let echoChannel = null

// Helper function to update session in list
const updateSessionInList = (sessionId, updates) => {
    const index = sessionsList.value.findIndex(s => s.session_id === sessionId || s.uuid === sessionId)
    if (index !== -1) {
        sessionsList.value[index] = { ...sessionsList.value[index], ...updates }
        console.log('✅ Session updated in list:', sessionId, updates)
    } else {
        console.warn('⚠️ Session not found in list:', sessionId)
    }
}

// Helper function to remove session from list
const removeSessionFromList = (uuid) => {
    const index = sessionsList.value.findIndex(s => s.uuid === uuid)
    if (index !== -1) {
        sessionsList.value.splice(index, 1)
        console.log('✅ Session removed from list:', uuid)
    }
}

// Helper function to add session to list
const addSessionToList = (session) => {
    sessionsList.value.unshift(session)
    console.log('✅ Session added to list:', session)
}

onMounted(() => {
    // Initialize Echo with better error handling
    const echo = window.Echo || getEchoInstance()

    if (!echo) {
        console.error('❌ Echo instance not available')
        alert('Real-time connection failed. Please refresh the page.')
        return
    }

    const channelName = `workspace.${props.workspaceId}`

    console.log('📡 Subscribing to Echo channel:', channelName)
    console.log('📡 Echo instance:', echo)
    console.log('📡 Workspace ID:', props.workspaceId)
    console.log('📡 Echo connector:', echo.connector)
    console.log('📡 Echo socket state:', echo.connector?.pusher?.connection?.state)

    // Wait for connection to be ready before subscribing
    const subscribeToChannel = () => {
        echoChannel = echo.channel(channelName)
        console.log('📡 Channel object:', echoChannel)
        console.log('📡 Subscribed at state:', echo.connector?.pusher?.connection?.state)

        // Setup listeners
        echoChannel.listen('.qr-code-generated', (data) => {
            console.log('📨 QR Code Generated Event received:', data)
            handleQRGenerated(data)
        })

        echoChannel.listen('.session-status-changed', (data) => {
            console.log('📨 Session Status Changed Event received:', data)
            handleSessionStatusChanged(data)
        })

        // Listen to Pusher events directly for debugging
        if (echo.connector?.pusher) {
            const pusherChannel = echo.connector.pusher.channel(channelName)
            if (pusherChannel) {
                pusherChannel.bind_global((eventName, data) => {
                    console.log('🎯 Pusher event received:', eventName, data)
                })
            }
        }
    }

    // Check if already connected
    if (echo.connector?.pusher?.connection?.state === 'connected') {
        console.log('✅ Already connected, subscribing immediately...')
        subscribeToChannel()
    } else {
        console.log('⏳ Waiting for connection before subscribing...')
        // Wait for connection
        echo.connector.pusher.connection.bind('connected', () => {
            console.log('✅ Connection established, now subscribing...')
            subscribeToChannel()
        })
    }

    // Also try to subscribe anyway (fallback)
    if (!echoChannel) {
        setTimeout(() => {
            if (!echoChannel) {
                console.log('⚠️ Force subscribing after timeout...')
                subscribeToChannel()
            }
        }, 1000)
    }

    // Listen for connection events
    if (echo.connector?.pusher) {
        echo.connector.pusher.connection.bind('connected', () => {
            console.log('✅ WebSocket connected')
        })

        echo.connector.pusher.connection.bind('disconnected', () => {
            console.log('❌ WebSocket disconnected')
        })

        echo.connector.pusher.connection.bind('error', (err) => {
            console.error('❌ WebSocket error:', err)
        })
    }

    console.log('✅ Echo channel subscribed successfully')
})

onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval)
    }
    if (echoChannel && props.workspaceId) {
        window.Echo.leaveChannel(`workspace.${props.workspaceId}`)
    }
})

const handleQRGenerated = (data) => {
    console.log('🔍 QR Code Event Data received:', data)
    console.log('🔍 Current workspace ID:', props.workspaceId)
    console.log('🔍 Event workspace ID:', data.workspace_id)

    if (data.workspace_id === props.workspaceId) {
        console.log('✅ QR Code data matches workspace, displaying...')

        // Clear timeout if set
        if (qrTimeout.value) {
            clearTimeout(qrTimeout.value)
            qrTimeout.value = null
        }

        qrCode.value = data.qr_code_base64
        countdown.value = data.expires_in_seconds || 300
        startCountdown()
    } else {
        console.log('❌ QR Code data workspace mismatch')
    }
}

const handleSessionStatusChanged = (data) => {
    console.log('📨 Session Status Changed:', data)

    if (data.workspace_id === props.workspaceId) {
        if (data.status === 'connected') {
            // Update session in list with connected status
            updateSessionInList(data.session_id, {
                status: 'connected',
                phone_number: data.phone_number || null,
                updated_at: data.metadata?.timestamp || new Date().toISOString()
            })

            // Close modal smoothly
            closeAddModal()

            console.log('✅ Session connected seamlessly, no page reload needed!')
        } else if (data.status === 'disconnected') {
            // Update session status in list
            updateSessionInList(data.session_id, {
                status: 'disconnected',
                updated_at: data.metadata?.timestamp || new Date().toISOString()
            })

            console.log('✅ Session disconnected seamlessly, list updated!')
        }
    }
}

const startCountdown = () => {
    clearInterval(countdownInterval)
    countdownInterval = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
            clearInterval(countdownInterval)
            qrCode.value = null
        }
    }, 1000)
}

const formatTime = (seconds) => {
    const minutes = Math.floor(seconds / 60)
    const remainingSeconds = seconds % 60
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString()
}

const addSession = async () => {
    try {
        showAddModal.value = true
        qrCode.value = null
        countdown.value = 300
        
        console.log('🔄 Creating new WhatsApp session...')
        
        const response = await axios.post('/settings/whatsapp-sessions', {
            provider_type: 'webjs'
        }, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        
        console.log('✅ Session created:', response.data)

        if (response.data.success) {
            currentSessionId.value = response.data.session.uuid

            // Check if QR code is already available
            if (response.data.qr_code) {
                console.log('📱 QR code received directly from response')
                qrCode.value = response.data.qr_code
                countdown.value = 300
                startCountdown()
            } else {
                console.log('📱 No QR code in response, waiting for WebSocket event...')
                // QR code will come via WebSocket event
            }
        }
    } catch (error) {
        console.error('❌ Failed to create session:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Failed to create WhatsApp session'
        alert(`Failed to create WhatsApp session: ${errorMessage}`)
        closeAddModal()
    }
}

const closeAddModal = () => {
    showAddModal.value = false
    qrCode.value = null
    countdown.value = 300
    clearInterval(countdownInterval)
}

const setPrimary = async (uuid) => {
    try {
        console.log('⭐ Setting primary session:', uuid)

        await axios.post(`/settings/whatsapp-sessions/${uuid}/set-primary`, {}, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })

        // Update all sessions: remove is_primary from current primary
        sessionsList.value.forEach(session => {
            session.is_primary = session.uuid === uuid
        })

        console.log('✅ Primary session updated seamlessly!')
    } catch (error) {
        console.error('Failed to set primary session:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Failed to set primary session'
        alert(`Failed to set primary session: ${errorMessage}`)
    }
}

const disconnect = async (uuid) => {
    if (confirm('Are you sure you want to disconnect this WhatsApp number?')) {
        try {
            console.log('🔌 Disconnecting session:', uuid)

            await axios.post(`/settings/whatsapp-sessions/${uuid}/disconnect`, {}, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })

            // Status will be updated via WebSocket event, but update optimistically
            updateSessionInList(uuid, {
                status: 'disconnecting...'
            })

            console.log('✅ Disconnect request sent, waiting for WebSocket update...')
        } catch (error) {
            console.error('Failed to disconnect session:', error)
            const errorMessage = error.response?.data?.message || error.message || 'Failed to disconnect session'
            alert(`Failed to disconnect session: ${errorMessage}`)
        }
    }
}

const deleteSession = async (uuid) => {
    if (confirm('Are you sure you want to delete this WhatsApp number? This action cannot be undone.')) {
        try {
            console.log('🗑️ Deleting session:', uuid)

            const response = await axios.delete(`/settings/whatsapp-sessions/${uuid}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })

            console.log('✅ Delete response:', response.data)

            // Remove from list immediately (seamless!)
            removeSessionFromList(uuid)

            console.log('✅ Session deleted seamlessly, no page reload!')
        } catch (error) {
            console.error('❌ Failed to delete session:', error)
            console.error('Error response:', error.response)

            if (error.response?.status === 404) {
                // Session not found - remove from list anyway
                removeSessionFromList(uuid)
                console.log('⚠️ Session not found on server, removed from list')
            } else {
                const errorMessage = error.response?.data?.message || error.message || 'Failed to delete session'
                alert(`Failed to delete session: ${errorMessage}`)
            }
        }
    }
}

const reconnect = async (uuid) => {
    try {
        const response = await axios.post(`/settings/whatsapp-sessions/${uuid}/reconnect`, {}, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        currentSessionId.value = uuid
        showAddModal.value = true
        qrCode.value = response.data.qr_code
        countdown.value = 300
        startCountdown()
    } catch (error) {
        console.error('Failed to reconnect session:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Failed to reconnect session'
        alert(`Failed to reconnect session: ${errorMessage}`)
    }
}

const regenerateQR = async () => {
    try {
        const response = await axios.post(`/settings/whatsapp-sessions/${currentSessionId.value}/regenerate-qr`, {}, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        qrCode.value = response.data.qr_code
        countdown.value = 300
        startCountdown()
    } catch (error) {
        console.error('Failed to regenerate QR code:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Failed to regenerate QR code'
        alert(`Failed to regenerate QR code: ${errorMessage}`)
    }
}
</script>

<style scoped>
/* Additional styles if needed */
</style>
