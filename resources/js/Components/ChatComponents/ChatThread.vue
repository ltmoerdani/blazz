<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { default as axios } from 'axios';
import ChatBubble from '@/Components/ChatComponents/ChatBubble.vue';
import MessageStatus from '@/Components/ChatComponents/MessageStatus.vue';
import { getEchoInstance } from '@/echo.js';

const props = defineProps({
    contactId: {
        type: Number,
        required: true
    },
    initialMessages: {
        type: Array,
        required: true
    },
    hasMoreMessages: {
        type: Boolean,
        required: true
    },
    initialNextPage: {
        type: Number,
        required: true
    }
});

const messages = ref(props.initialMessages);
const loading = ref(false);
const nextPage = ref(props.initialNextPage);
const hasMore = ref(props.hasMoreMessages);
const echo = ref(null);
const isTyping = ref(false);
const typingUser = ref(null);

const loadMoreMessages = async () => {
    if (loading.value || !hasMore.value) return;

    loading.value = true;
    try {
        const response = await axios.get(`/chats/${props.contactId}/messages?page=${nextPage.value}`);
        messages.value = [...response.data.messages, ...messages.value];
        hasMore.value = response.data.hasMoreMessages;
        nextPage.value = response.data.nextPage;
    } catch (error) {
        console.error('Error loading messages:', error);
    } finally {
        loading.value = false;
    }
};

// Initialize Echo listeners for real-time messaging
const initializeEchoListeners = () => {
    try {
        echo.value = getEchoInstance();

        if (!echo.value) {
            console.error('Echo instance not available');
            return;
        }

        console.log('🔊 Setting up real-time listeners for contact:', props.contactId);

        // Listen for message status updates (✓ ✓✓ ✓✓✓)
        echo.value.private(`chat.${props.contactId}`)
            .listen('.message.status.updated', (e) => {
                console.log('📊 Message status updated:', e);
                updateMessageStatus(e.message_id, e.status, e.timestamp);
            });

        // Listen for message delivery events
        echo.value.private(`chat.${props.contactId}`)
            .listen('.message.delivered', (e) => {
                console.log('✅ Message delivered:', e);
                updateMessageStatus(e.message_id, 'delivered', e.delivered_at);
            });

        // Listen for message read events
        echo.value.private(`chat.${props.contactId}`)
            .listen('.message.read', (e) => {
                console.log('👁️ Message read:', e);
                updateMessageStatus(e.message_id, 'read', e.read_at);
            });

        // Listen for typing indicators
        echo.value.private(`chat.${props.contactId}`)
            .listen('.typing.indicator', (e) => {
                console.log('⌨️ Typing indicator:', e);
                handleTypingIndicator(e);
            });

        // Listen for new messages (if implemented)
        echo.value.private(`chat.${props.contactId}`)
            .listen('.new.message', (e) => {
                console.log('💬 New message received:', e);
                addNewMessage(e.message);
            });

        console.log('✅ Real-time listeners established successfully');

    } catch (error) {
        console.error('❌ Error initializing Echo listeners:', error);
    }
};

// Update message status in the UI instantly
const updateMessageStatus = (messageId, status, timestamp) => {
    const messageIndex = messages.value.findIndex(msg =>
        msg[0]?.value?.whatsapp_message_id === messageId ||
        msg[0]?.value?.id === messageId
    );

    if (messageIndex !== -1) {
        const message = messages.value[messageIndex][0].value;

        // Update status with immediate UI feedback
        message.message_status = status;

        if (timestamp) {
            message.delivered_at = status === 'delivered' ? timestamp : message.delivered_at;
            message.read_at = status === 'read' ? timestamp : message.read_at;
        }

        console.log(`🔄 Updated message ${messageId} status to ${status}`);
    }
};

// Handle typing indicators
const handleTypingIndicator = (event) => {
    isTyping.value = event.is_typing;
    typingUser.value = event.is_typing ? {
        name: event.contact_name || 'Someone',
        timestamp: event.timestamp
    } : null;

    // Auto-hide typing indicator after 3 seconds of inactivity
    if (event.is_typing) {
        clearTimeout(window.typingTimeout);
        window.typingTimeout = setTimeout(() => {
            isTyping.value = false;
            typingUser.value = null;
        }, 3000);
    }
};

// Add new message to chat in real-time
const addNewMessage = (messageData) => {
    // Prevent duplicate messages
    const exists = messages.value.some(msg =>
        msg[0]?.value?.id === messageData.id ||
        msg[0]?.value?.whatsapp_message_id === messageData.whatsapp_message_id
    );

    if (!exists) {
        const newMessage = [{
            type: 'chat',
            value: {
                ...messageData,
                message_status: messageData.message_status || 'delivered',
                created_at: messageData.created_at || new Date().toISOString()
            }
        }];

        messages.value.push(newMessage);

        // Scroll to bottom if needed
        setTimeout(() => {
            const chatContainer = document.querySelector('.chat-thread-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }, 100);
    }
};

// Setup and cleanup Echo listeners
onMounted(() => {
    console.log('🚀 ChatThread mounted for contact:', props.contactId);
    initializeEchoListeners();
});

onUnmounted(() => {
    // Cleanup Echo listeners
    if (echo.value) {
        try {
            echo.value.leave(`chat.${props.contactId}`);
            console.log('🧹 Cleaned up Echo listeners for contact:', props.contactId);
        } catch (error) {
            console.error('Error cleaning up Echo listeners:', error);
        }
    }

    // Clear typing timeout
    if (window.typingTimeout) {
        clearTimeout(window.typingTimeout);
    }
});
</script>

<template>
    <div class="py-4 md:py-4 relative px-6 md:px-10">
        <div v-if="hasMore" 
             class="text-center py-2">
            <div v-if="loading" 
                 class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            </div>
            <button v-else
                    @click="loadMoreMessages"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
                Load More Messages
            </button>
        </div>
        
        <div v-for="(chat, index) in messages"
             :key="index"
             class="flex flex-grow flex-col"
             :class="chat[0].type === 'ticket' ? 'justify-center' : 'justify-end'">

            <!-- Chat messages with status indicators -->
            <div v-if="chat[0].type === 'chat'" class="flex items-end gap-2">
                <ChatBubble
                    :content="chat[0].value"
                    :type="chat[0].value.type" />

                <!-- Message status component for outbound messages -->
                <MessageStatus
                    v-if="chat[0].value.type === 'outbound'"
                    :status="chat[0].value.message_status || 'pending'"
                    :timestamp="chat[0].value.created_at"
                    :show-indicators="true"
                    :show-timestamp="true"
                    size="small" />
            </div>

            <!-- Ticket messages -->
            <div v-if="chat[0].type === 'ticket'" class="py-2">
                <div class="text-center font-light text-sm border-b border-t py-2 border-dashed border-black">
                    <div>{{ chat[0].value.description }} </div>
                    <div class="text-xs">{{ chat[0].value.created_at }}</div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="chat[0].type === 'notes'" class="py-2 bg-orange-100 my-2 rounded-lg p-2 w-[fit-content] ml-auto">
                <div class="text-right font-light text-sm">
                    <div>{{ chat[0].value.content }}</div>
                    <div class="flex items-center justify-between mt-2 space-x-4">
                        <p class="text-gray-500 text-xs text-right leading-none">{{ chat[0].value.created_at }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div v-if="isTyping && typingUser" class="flex items-center gap-2 px-4 py-2">
            <div class="typing-indicator">
                <span class="typing-text">{{ typingUser.name }} is typing</span>
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Typing Indicator Styles */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background-color: #f3f4f6;
    border-radius: 18px;
    max-width: fit-content;
}

.typing-text {
    font-size: 14px;
    color: #6b7280;
    font-style: italic;
}

.typing-dots {
    display: flex;
    gap: 3px;
    align-items: center;
}

.typing-dots span {
    width: 6px;
    height: 6px;
    background-color: #9ca3af;
    border-radius: 50%;
    animation: typingAnimation 1.4s infinite ease-in-out;
}

.typing-dots span:nth-child(1) {
    animation-delay: 0s;
}

.typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typingAnimation {
    0%, 60%, 100% {
        transform: translateY(0);
        opacity: 0.5;
    }
    30% {
        transform: translateY(-10px);
        opacity: 1;
    }
}

/* Dark mode support for typing indicator */
@media (prefers-color-scheme: dark) {
    .typing-indicator {
        background-color: #374151;
    }

    .typing-text {
        color: #d1d5db;
    }

    .typing-dots span {
        background-color: #9ca3af;
    }
}

/* Message container improvements */
.flex.items-end.gap-2 {
    align-items: flex-end;
}

/* Status positioning */
.message-status {
    flex-shrink: 0;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .typing-indicator {
        padding: 6px 10px;
    }

    .typing-text {
        font-size: 12px;
    }

    .typing-dots span {
        width: 4px;
        height: 4px;
    }
}

/* Smooth transitions */
.typing-indicator {
    transition: all 0.2s ease-in-out;
}

/* Loading states */
.loading .typing-indicator {
    opacity: 0.6;
}
</style>