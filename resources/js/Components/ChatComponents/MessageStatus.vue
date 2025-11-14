<template>
    <div class="message-status" :class="statusClass">
        <!-- WhatsApp-style status indicators -->
        <div class="status-indicators" v-if="showIndicators">
            <!-- Clock icon for pending/sending -->
            <svg v-if="isPending" class="status-icon clock-icon" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M12 6v6l4 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>

            <!-- Single check for sent -->
            <svg v-else-if="isSent" class="status-icon check-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor"/>
            </svg>

            <!-- Double check for delivered -->
            <svg v-else-if="isDelivered" class="status-icon double-check-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor"/>
                <path d="M15 20.17L10.83 16l-1.42 1.41L15 23l12-12-1.41-1.41L15 20.17z" fill="currentColor"/>
            </svg>

            <!-- Blue double check for read -->
            <svg v-else-if="isRead" class="status-icon double-check-icon read" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor"/>
                <path d="M15 20.17L10.83 16l-1.42 1.41L15 23l12-12-1.41-1.41L15 20.17z" fill="currentColor"/>
            </svg>

            <!-- Error icon for failed -->
            <svg v-else-if="isFailed" class="status-icon error-icon" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>

        <!-- Status text (optional, for accessibility) -->
        <span v-if="showText" class="status-text">{{ statusText }}</span>

        <!-- Timestamp -->
        <span v-if="showTimestamp && timestamp" class="timestamp">
            {{ formatTimestamp(timestamp) }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: {
        type: String,
        default: 'pending',
        validator: (value) => ['pending', 'sent', 'delivered', 'read', 'failed'].includes(value)
    },
    timestamp: {
        type: [String, Number, Date],
        default: null
    },
    showIndicators: {
        type: Boolean,
        default: true
    },
    showText: {
        type: Boolean,
        default: false
    },
    showTimestamp: {
        type: Boolean,
        default: true
    },
    size: {
        type: String,
        default: 'medium',
        validator: (value) => ['small', 'medium', 'large'].includes(value)
    }
})

// Computed properties for status checking
const isPending = computed(() => ['pending', 'sending'].includes(props.status))
const isSent = computed(() => props.status === 'sent')
const isDelivered = computed(() => props.status === 'delivered')
const isRead = computed(() => props.status === 'read')
const isFailed = computed(() => props.status === 'failed')

// CSS classes based on status
const statusClass = computed(() => ({
    [`status-${props.status}`]: true,
    [`size-${props.size}`]: true,
    'with-timestamp': props.showTimestamp && props.timestamp,
    'failed': isFailed.value
}))

// Status text for accessibility
const statusText = computed(() => {
    const statusMap = {
        pending: 'Sending...',
        sent: 'Sent',
        delivered: 'Delivered',
        read: 'Read',
        failed: 'Failed to send'
    }
    return statusMap[props.status] || props.status
})

// Format timestamp for display
const formatTimestamp = (timestamp) => {
    if (!timestamp) return ''

    const date = new Date(timestamp)

    // If today, show time only
    const today = new Date()
    const isToday = date.toDateString() === today.toDateString()

    if (isToday) {
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        })
    }

    // If this week, show day name
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)
    if (date > weekAgo) {
        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        })
    }

    // Otherwise, show date
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric'
    })
}
</script>

<style scoped>
.message-status {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-1, 4px);
    font-size: 12px;
    color: var(--color-text-secondary, #667085);
    transition: all 0.2s ease;
}

/* Size variants */
.size-small {
    font-size: 10px;
}

.size-medium {
    font-size: 12px;
}

.size-large {
    font-size: 14px;
}

/* Status indicators container */
.status-indicators {
    display: flex;
    align-items: center;
}

/* Status icons */
.status-icon {
    width: 16px;
    height: 16px;
    transition: all 0.3s ease;
}

.size-small .status-icon {
    width: 12px;
    height: 12px;
}

.size-large .status-icon {
    width: 20px;
    height: 20px;
}

/* Clock icon (pending) */
.clock-icon {
    color: #9ca3af;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Single check (sent) */
.check-icon {
    color: #9ca3af;
}

/* Double check (delivered) */
.double-check-icon {
    color: #9ca3af;
}

/* Double check (read) - blue */
.double-check-icon.read {
    color: #53bdeb;
}

/* Error icon */
.error-icon {
    color: #ef4444;
}

/* Status specific classes */
.status-pending {
    color: #9ca3af;
}

.status-sent {
    color: #9ca3af;
}

.status-delivered {
    color: #9ca3af;
}

.status-read {
    color: #53bdeb;
}

.status-failed {
    color: #ef4444;
}

/* Failed status styling */
.failed {
    color: #ef4444;
}

.failed .status-icon {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-2px); }
    75% { transform: translateX(2px); }
}

/* Timestamp */
.timestamp {
    font-size: 11px;
    color: var(--color-text-tertiary, #9ca3af);
    white-space: nowrap;
}

.status-text {
    font-size: 10px;
    color: var(--color-text-secondary, #667085);
}

/* Hover effects */
.message-status:hover .status-icon {
    transform: scale(1.1);
}

/* RTL support */
.message-status[dir="rtl"] .status-indicators {
    order: 2;
}

.message-status[dir="rtl"] .timestamp {
    order: 1;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .message-status {
        color: var(--color-text-secondary-dark, #9ca3af);
    }

    .timestamp {
        color: var(--color-text-tertiary-dark, #6b7280);
    }

    .status-text {
        color: var(--color-text-secondary-dark, #9ca3af);
    }
}

/* Animation for status changes */
.status-icon {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Focus styles for accessibility */
.message-status:focus-within {
    outline: 2px solid var(--color-primary, #3b82f6);
    outline-offset: 2px;
    border-radius: 4px;
}
</style>