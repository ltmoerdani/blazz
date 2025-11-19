<template>
    <AppLayout>
        <div class="p-4 md:p-8 rounded-[5px] h-full overflow-y-auto">
            <div class="flex justify-between capitalize">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Campaign details') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6">
                        <span class="ml-1 mt-1">{{ $t('Ref') }}: {{ props.campaign.uuid }}</span>
                        <!-- Real-time indicator -->
                        <span v-if="isConnected" class="ml-3 flex items-center text-green-600">
                            <span class="relative flex h-2 w-2 mr-1">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            <span class="text-xs">{{ $t('Live') }}</span>
                        </span>
                    </p>
                </div>
                <div class="space-x-2">
                    <a :href="'/campaigns/export/' + props.campaign.uuid" class="rounded-md bg-secondary px-3 py-2 text-sm text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Export as CSV') }}
                    </a>

                    <Link href="/campaigns" class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Back') }}
                    </Link>
                </div>
            </div>

            <div class="md:flex md:space-x-4">
                <div class="md:w-[70%] capitalize">
                    <!-- Statistics Cards with Real-time Updates -->
                    <div class="flex w-[100%] mb-8 rounded-lg">
                        <div class="stat-card w-full rounded-tl-lg rounded-bl-lg text-center bg-white py-8 border" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold">{{ statistics.total_message_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Messages') }}</h4>
                        </div>
                        <div class="stat-card w-full text-center bg-white py-8 border hover:bg-blue-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-blue-600">{{ statistics.total_sent_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Sent') }}</h4>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ statistics.success_rate }}% 
                                <span v-if="statistics.pending_count > 0" class="text-orange-500">
                                    ({{ statistics.pending_count }} pending)
                                </span>
                            </div>
                        </div>
                        <div class="stat-card w-full text-center bg-white py-8 border hover:bg-green-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-green-600">{{ statistics.total_delivered_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Delivered') }}</h4>
                            <div class="text-xs text-gray-500 mt-1">{{ statistics.delivery_rate }}%</div>
                        </div>
                        <div class="stat-card w-full bg-white text-center py-8 border hover:bg-indigo-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-indigo-600">{{ statistics.total_read_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Read') }}</h4>
                            <div class="text-xs text-gray-500 mt-1">{{ statistics.read_rate }}%</div>
                        </div>
                        <div class="stat-card w-full rounded-tr-lg rounded-br-lg bg-white text-center py-8 border hover:bg-red-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-red-600">{{ statistics.total_failed_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Failed') }}</h4>
                        </div>
                    </div>

                    <!-- Last Updated Timestamp -->
                    <div v-if="lastUpdated" class="text-xs text-gray-500 mb-4 text-right">
                        {{ $t('Last updated') }}: {{ formatTimestamp(lastUpdated) }}
                    </div>

                    <!-- Table Component-->
                    <CampaignLogTable :rows="props.rows" :filters="props.filters" :uuid="props.campaign.uuid"/>
                </div>
                <div class="md:w-[30%]">
                    <div class="w-full rounded-lg bg-white pt-4 pb-8 border px-4 space-y-1 capitalize">
                        <h2 class="mb-2">{{ $t('Campaign details') }}</h2>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Campaign name') }}</h3>
                            <p>{{ props.campaign?.name }}</p>
                        </div>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Campaign Type') }}</h3>
                            <p>{{ props.campaign?.campaign_type === 'direct' ? $t('Direct Message') : $t('Template-based') }}</p>
                        </div>
                        <div v-if="props.campaign?.template" class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Template') }}</h3>
                            <p>{{ props.campaign?.template?.name }}</p>
                        </div>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Recipients') }}</h3>
                            <p>{{ props.campaign.contact_group_id === '0' ? 'All Contacts' : props.campaign?.contact_group?.name }}</p>
                        </div>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Time scheduled') }}</h3>
                            <p>{{ props.campaign.scheduled_at }}</p>
                        </div>
                    </div>

                    <div class="w-full rounded-lg p-5 mt-5 border chat-bg">
                        <!-- Direct Message Preview -->
                        <div v-if="props.campaign?.campaign_type === 'direct'" class="mr-auto rounded-lg rounded-tl-none my-1 p-1 text-sm bg-white flex flex-col relative speech-bubble-left w-[25em]">
                            <div v-if="props.campaign.header_type && props.campaign.header_type !== 'text'" class="mb-4 bg-[#ccd0d5] flex justify-center py-8 rounded">
                                <img v-if="props.campaign.header_type === 'image'" :src="'/images/image-placeholder.png'">
                                <img v-if="props.campaign.header_type === 'video'" :src="'/images/video-placeholder.png'">
                                <img v-if="props.campaign.header_type === 'document'" :src="'/images/document-placeholder.png'">
                            </div>
                            <h2 v-else-if="props.campaign.header_text" class="text-gray-700 text-sm mb-1 px-2 normal-case whitespace-pre-wrap">{{ props.campaign.header_text }}</h2>
                            <p class="px-2 normal-case whitespace-pre-wrap">{{ props.campaign.body_text }}</p>
                            <div class="text-[#8c8c8c] mt-1 px-2">
                                <span class="text-[13px]">{{ props.campaign.footer_text }}</span>
                                <span class="text-right text-xs leading-none float-right" :class="props.campaign.footer_text ? 'mt-2' : ''">9:15</span>
                            </div>
                        </div>
                        
                        <!-- Template-based Preview -->
                        <WhatsappTemplate v-else :parameters="JSON.parse(props.campaign.metadata)" :placeholder="false" :visible="true"/>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
import AppLayout from "./../Layout/App.vue";
import CampaignLogTable from '@/Components/Tables/CampaignLogTable.vue';
import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
import { Link } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps(['campaign', 'rows', 'filters']);
const page = usePage();

// Reactive statistics (will be updated in real-time)
const statistics = ref({
    total_message_count: props.campaign.total_message_count,
    total_sent_count: props.campaign.total_sent_count,
    total_delivered_count: props.campaign.total_delivered_count,
    total_read_count: props.campaign.total_read_count,
    total_failed_count: props.campaign.total_failed_count,
    pending_count: 0,
    delivery_rate: 0,
    read_rate: 0,
    success_rate: 0
});

// WebSocket connection state
const isConnected = ref(false);
const isUpdating = ref(false);
const lastUpdated = ref(null);
let workspaceChannel = null;
let campaignChannel = null;
let updateAnimationTimeout = null;

// Format timestamp for display
const formatTimestamp = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // seconds

    if (diff < 60) return `${diff} seconds ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)} minutes ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
    return date.toLocaleString();
};

// Handle statistics update from WebSocket
const handleStatisticsUpdate = (event) => {
    console.log('📨 Campaign statistics update received', event);

    // Only update if it's for this campaign
    if (event.campaign_uuid === props.campaign.uuid) {
        // Trigger update animation
        isUpdating.value = true;
        if (updateAnimationTimeout) {
            clearTimeout(updateAnimationTimeout);
        }
        updateAnimationTimeout = setTimeout(() => {
            isUpdating.value = false;
        }, 500);

        // Update statistics
        statistics.value = {
            total_message_count: event.statistics.total_message_count,
            total_sent_count: event.statistics.total_sent_count,
            total_delivered_count: event.statistics.total_delivered_count,
            total_read_count: event.statistics.total_read_count,
            total_failed_count: event.statistics.total_failed_count,
            pending_count: event.statistics.pending_count || 0,
            delivery_rate: event.statistics.delivery_rate || 0,
            read_rate: event.statistics.read_rate || 0,
            success_rate: event.statistics.success_rate || 0
        };

        lastUpdated.value = event.statistics.updated_at || event.timestamp;

        console.log('✅ Campaign statistics updated in UI', statistics.value);
    }
};

onMounted(() => {
    const workspaceId = page.props.auth.workspace.id;
    const campaignUuid = props.campaign.uuid;

    console.log('📊 Subscribing to campaign statistics updates', {
        workspace_id: workspaceId,
        campaign_uuid: campaignUuid
    });

    try {
        // Subscribe to workspace channel for campaign updates
        workspaceChannel = window.Echo.channel(`workspace.${workspaceId}`)
            .listen('.campaign.statistics.updated', handleStatisticsUpdate);

        // Also subscribe to campaign-specific channel
        campaignChannel = window.Echo.channel(`campaign.${campaignUuid}`)
            .listen('.campaign.statistics.updated', handleStatisticsUpdate);

        // Mark as connected
        isConnected.value = true;
        console.log('✅ Successfully subscribed to campaign statistics updates');

    } catch (error) {
        console.error('❌ Failed to subscribe to campaign statistics', error);
        isConnected.value = false;
    }
});

onUnmounted(() => {
    console.log('🔌 Cleaning up campaign statistics subscriptions');

    if (updateAnimationTimeout) {
        clearTimeout(updateAnimationTimeout);
    }

    if (workspaceChannel || campaignChannel) {
        const workspaceId = page.props.auth.workspace.id;
        const campaignUuid = props.campaign.uuid;
        
        try {
            window.Echo.leave(`workspace.${workspaceId}`);
            window.Echo.leave(`campaign.${campaignUuid}`);
            console.log('✅ Successfully unsubscribed from campaign statistics');
        } catch (error) {
            console.error('❌ Error unsubscribing from channels', error);
        }
    }

    isConnected.value = false;
});
</script>

<style scoped>
/* Animation for updating statistics */
.stat-card-updating {
    animation: pulse-scale 0.5s ease-in-out;
}

@keyframes pulse-scale {
    0%, 100% { 
        transform: scale(1); 
    }
    50% { 
        transform: scale(1.05); 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}

/* Hover effect for stat cards */
.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Pulsing live indicator */
@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

.animate-ping {
    animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>