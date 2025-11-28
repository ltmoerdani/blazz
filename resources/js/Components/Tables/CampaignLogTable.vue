<script setup>
    import 'vue3-toastify/dist/index.css';
    import { ref, computed } from 'vue';
    import debounce from 'lodash/debounce';
    import { router } from '@inertiajs/vue3';
    import Modal from '@/Components/Modal.vue';
    import Table from '@/Components/Table.vue';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        },
        uuid: {
            type: String
        },
        speedTierConfig: {
            type: Object,
            default: null
        },
        scheduledAt: {
            type: String,
            default: null
        }
    });
    
    const params = ref({
        search: props.filters.search,
    });

    const logs = ref(null);
    const messageStatus = ref(null);
    const isOpenModal = ref(false);
    const isSearching = ref(false);
    const emit = defineEmits(['delete']);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        router.visit('/campaigns/' + props.uuid, {
            method: 'get',
            data: params.value,
        })
    }

    const openModal = (status, value) => {
        messageStatus.value = status;
        logs.value = value;
        isOpenModal.value = true;
    }

    const getStatus = (metadata) => {
        return JSON.parse(metadata).status;
    }

    const getErrorDetails = (metadata) => {
        return JSON.parse(metadata);
    }

    /**
     * Parse date string to Date object
     * Handles format: "2025-11-28 09:09:31"
     */
    const parseScheduledDate = () => {
        if (!props.scheduledAt) return null;
        
        // Handle both formats: "2025-11-28 09:09:31" and ISO format
        let dateStr = props.scheduledAt;
        
        // If format is "YYYY-MM-DD HH:MM:SS", convert to ISO format
        if (dateStr.includes(' ') && !dateStr.includes('T')) {
            dateStr = dateStr.replace(' ', 'T');
        }
        
        const date = new Date(dateStr);
        return isNaN(date.getTime()) ? null : date;
    }

    /**
     * Store pre-generated random intervals for consistency
     */
    const randomIntervals = ref({});

    /**
     * Generate random interval for a specific index
     * Uses seeded random based on index for consistency during re-renders
     */
    const getRandomInterval = (index) => {
        if (!props.speedTierConfig) return 0;
        
        // Check if already generated
        if (randomIntervals.value[index] !== undefined) {
            return randomIntervals.value[index];
        }
        
        const minMs = props.speedTierConfig.interval_min_ms;
        const maxMs = props.speedTierConfig.interval_max_ms;
        
        // Generate random interval between min and max
        const baseInterval = minMs + Math.random() * (maxMs - minMs);
        
        // Apply ±25% variance for more realistic feel
        const variance = baseInterval * 0.25;
        const finalInterval = baseInterval + (Math.random() * 2 - 1) * variance;
        
        // Ensure minimum 1 second
        const intervalMs = Math.max(1000, finalInterval);
        
        // Store for consistency
        randomIntervals.value[index] = intervalMs;
        
        return intervalMs;
    }

    /**
     * Calculate estimated send time for each contact
     * Based on position in queue and accumulated random intervals
     */
    const calculateEstimatedTime = (index) => {
        if (!props.speedTierConfig || !props.scheduledAt) {
            return null;
        }

        // Parse scheduled time
        const baseTime = parseScheduledDate();
        if (!baseTime) {
            return null;
        }

        // Calculate batch breaks
        const batchSize = props.speedTierConfig.batch_size || 20;
        const batchBreakMs = props.speedTierConfig.batch_break_ms || 180000;

        // Accumulate all delays up to this index
        let totalDelayMs = 0;
        for (let i = 0; i < index; i++) {
            // Add the random interval for each previous message
            totalDelayMs += getRandomInterval(i);
            
            // Add batch break if needed (after every batchSize messages)
            if ((i + 1) % batchSize === 0) {
                totalDelayMs += batchBreakMs;
            }
        }

        // Add delay to base time
        const estimatedTime = new Date(baseTime.getTime() + totalDelayMs);
        
        return estimatedTime;
    }

    /**
     * Format estimated time for display - full date and time
     */
    const formatEstimatedTime = (index) => {
        const estimated = calculateEstimatedTime(index);
        if (!estimated) return '-';
        
        // Format as YYYY-MM-DD HH:MM:SS
        const year = estimated.getFullYear();
        const month = String(estimated.getMonth() + 1).padStart(2, '0');
        const day = String(estimated.getDate()).padStart(2, '0');
        const hours = String(estimated.getHours()).padStart(2, '0');
        const minutes = String(estimated.getMinutes()).padStart(2, '0');
        const seconds = String(estimated.getSeconds()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    /**
     * Get the actual random interval for this specific contact in seconds
     */
    const getIntervalDisplay = (index) => {
        if (!props.speedTierConfig) return '-';
        
        // First message has no interval (it's the starting point)
        if (index === 0) return '-';
        
        // Get the random interval for the previous position (delay before this message)
        const intervalMs = getRandomInterval(index - 1);
        const intervalSec = Math.round(intervalMs / 1000);
        
        // Check if this is after a batch break
        const batchSize = props.speedTierConfig.batch_size || 20;
        if (index % batchSize === 0) {
            const breakMin = Math.round(props.speedTierConfig.batch_break_ms / 1000 / 60);
            return `${intervalSec}s + ${breakMin}m break`;
        }
        
        return `${intervalSec}s`;
    }

    /**
     * Check if position is after a batch break
     */
    const isAfterBatchBreak = (index) => {
        if (!props.speedTierConfig) return false;
        const batchSize = props.speedTierConfig.batch_size || 20;
        return index > 0 && index % batchSize === 0;
    }

    /**
     * Get the actual position index for a row
     * Handles pagination offset safely
     */
    const getRowPosition = (loopIndex) => {
        const currentPage = props.rows?.current_page || props.rows?.meta?.current_page || 1;
        const perPage = props.rows?.per_page || props.rows?.meta?.per_page || 10;
        return ((currentPage - 1) * perPage) + loopIndex;
    }

    /**
     * Get appropriate CSS class for status badge
     * @param {Object} item - Campaign log item
     * @returns {string} CSS classes for status badge
     */
    const getStatusClass = (item) => {
        // Determine the effective status to display
        const status = getEffectiveStatus(item);
        
        const statusClasses = {
            'success': 'bg-green-700 text-white',
            'sent': 'bg-green-600 text-white',
            'delivered': 'bg-blue-600 text-white',
            'read': 'bg-purple-600 text-white',
            'pending': 'bg-yellow-500 text-white',
            'queued': 'bg-orange-500 text-white',
            'failed': 'bg-red-500 text-white',
            'error': 'bg-red-400 text-white'
        };
        
        return statusClasses[status] || 'bg-gray-500 text-white';
    }

    /**
     * Get the effective status from campaign log item
     * Prioritizes chat status when available for more detailed delivery info
     * @param {Object} item - Campaign log item
     * @returns {string} Effective status
     */
    const getEffectiveStatus = (item) => {
        // If log status is success and we have chat with status, use chat status
        // This gives us more detailed delivery info (sent, delivered, read)
        if (item.status === 'success' && item.chat?.status) {
            return item.chat.status;
        }
        // Otherwise use the log status (pending, success, failed)
        return item.status || 'pending';
    }

    /**
     * Get human-readable status label
     * @param {Object} item - Campaign log item
     * @returns {string} Status label to display
     */
    const getStatusLabel = (item) => {
        return getEffectiveStatus(item);
    }
</script>
<template>
    <div class="bg-white flex items-center shadow-sm h-10 w-80 rounded-[0.5rem] mb-6 text-sm">
        <span class="pl-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="outline-none px-4 w-full" :placeholder="$t('Search campaigns')">
        <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="pr-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
        </button>
        <span v-if="isSearching" class="pr-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
        </span>
    </div>
    <Table :rows="rows">
        <TableHeader>
            <TableHeaderRow>
                <TableHeaderRowItem :position="'first'" class="hidden sm:table-cell w-8">#</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Contact') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Phone') }}</TableHeaderRowItem>
                <TableHeaderRowItem v-if="speedTierConfig" class="hidden md:table-cell">{{ $t('Est. Time') }}</TableHeaderRowItem>
                <TableHeaderRowItem v-if="speedTierConfig" class="hidden lg:table-cell">{{ $t('Interval') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Last updated') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index">
                <TableBodyRowItem :position="'first'" class="hidden sm:table-cell text-gray-400 text-xs">
                    {{ getRowPosition(index) + 1 }}
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">{{ item.contact.full_name }}</TableBodyRowItem>
                <TableBodyRowItem>
                    {{ item.contact.phone }}
                </TableBodyRowItem>
                <TableBodyRowItem v-if="speedTierConfig" class="hidden md:table-cell">
                    <div class="text-sm">
                        <span class="font-medium">{{ formatEstimatedTime(getRowPosition(index)) }}</span>
                        <div v-if="isAfterBatchBreak(getRowPosition(index))" class="text-xs text-orange-500">
                            ☕ after break
                        </div>
                    </div>
                </TableBodyRowItem>
                <TableBodyRowItem v-if="speedTierConfig" class="hidden lg:table-cell">
                    <span class="text-xs px-2 py-1 bg-gray-100 rounded" :class="{ 'bg-orange-50 text-orange-700': isAfterBatchBreak(getRowPosition(index)) }">
                        {{ getIntervalDisplay(getRowPosition(index)) }}
                    </span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <span v-if="item.status === 'success' && item.chat" class="border-b border-dashed border-black">{{ item.chat.created_at }}</span>
                    <span v-else class="border-b border-dashed border-black">{{ item.created_at }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <span class="px-2 py-1 text-xs rounded-md capitalize" :class="getStatusClass(item)">
                        {{ getStatusLabel(item) }}
                    </span>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <div @click="openModal(item.status, item.status === 'success' ? item.chat?.logs : item.metadata)" class="flex items-center underline cursor-pointer">
                        <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><g fill="currentColor"><path d="M11 10.98a1 1 0 1 1 2 0v6a1 1 0 1 1-2 0zm1-4.929a1 1 0 1 0 0 2a1 1 0 0 0 0-2"/><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10s10-4.477 10-10S17.523 2 12 2M4 12a8 8 0 1 0 16 0a8 8 0 0 0-16 0" clip-rule="evenodd"/></g></svg>
                        <span>{{ $t('More info') }}</span>
                    </div>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>
    <Modal :label="$t('Message info')" :isOpen="isOpenModal">
        <div class="max-w-md w-full space-y-8">
            <div class="mt-8 space-y-2">
                <div v-if="messageStatus === 'success'" v-for="(log, index) in logs" class="text-sm border-b pb-2">
                    <div class="flex items-center capitalize">
                        <svg class="mr-1" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m1.75 9.75l2.5 2.5m3.5-4l2.5-2.5m-4.5 4l2.5 2.5l6-6.5"/></svg>
                        <span>{{ $t(getStatus(log.metadata)) }}</span>
                    </div>
                    <div>{{ log.created_at }}</div>
                </div>
                <div v-else-if="messageStatus === 'failed'">
                    <div class="text-sm mb-3 bg-red-800 p-2 rounded text-white">Error: {{ getErrorDetails(logs).data.error.message }}</div>
                    <div v-if="getErrorDetails(logs).data?.error?.error_data?.details" class="text-sm">{{ getErrorDetails(logs).data?.error?.error_data?.details }}</div>
                    <div v-else>{{ getErrorDetails(logs).data.error.message }}</div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <div class="mt-2 w-full">
                <button type="button" @click="isOpenModal = false" class="inline-flex float-right justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">{{ $t('Close') }}</button>
            </div>
        </div>
    </Modal>
</template>
  