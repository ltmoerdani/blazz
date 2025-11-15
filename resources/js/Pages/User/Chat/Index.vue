<template>
    <AppLayout v-slot:default="slotProps">
        <div class="md:flex md:flex-grow md:overflow-hidden">
            <div class="md:w-[30%] md:flex flex-col h-full bg-white border-r border-l" :class="contact ? 'hidden' : ''">
                <ChatTable 
                    :rows="rows" 
                    :filters="props.filters" 
                    :rowCount="props.rowCount" 
                    :ticketingIsEnabled="ticketingIsEnabled" 
                    :status="props?.status" 
                    :chatSortDirection="props.chat_sort_direction"
                    @contact-selected="selectContact"
                />
            </div>
            <div class="min-w-0 bg-cover flex flex-col chat-bg" :class="contact ? 'h-screen md:w-[70%]' : 'md:h-screen md:w-[70%]'">
                <ChatHeader 
                    v-if="contact" 
                    :ticketingIsEnabled="ticketingIsEnabled" 
                    :contact="contact" 
                    :displayContactInfo="displayContactInfo" 
                    :ticket="ticket" 
                    :addon="addon" 
                    @toggleView="toggleContactView" 
                    @deleteThread="deleteThread" 
                    @closeThread="closeThread"
                />
                <div v-if="contact && !displayTemplate" class="flex-1 overflow-y-auto" ref="scrollContainer2">
                    <!-- Loading Skeleton for instant feedback -->
                    <div v-if="loadingThread && !displayContactInfo" class="p-4 space-y-3 animate-pulse">
                        <div v-for="n in 5" :key="n" class="flex" :class="n % 2 === 0 ? 'justify-end' : 'justify-start'">
                            <div :class="n % 2 === 0 ? 'bg-green-100' : 'bg-gray-100'" class="rounded-lg p-3 max-w-xs">
                                <div class="h-4 bg-gray-300 rounded w-48 mb-2"></div>
                                <div class="h-3 bg-gray-300 rounded w-32"></div>
                            </div>
                        </div>
                    </div>
                    
                    <ChatThread 
                        v-if="!displayContactInfo && !loadingThread && !displayTemplate"
                        ref="chatThreadRef"
                        :contactId="contact.id"
                        :workspaceId="props.workspaceId"
                        :initialMessages="chatThread"
                        :hasMoreMessages="hasMoreMessages"
                        :initialNextPage="nextPage"
                        @message-sent="handleMessageSent"
                        @retry-message="handleRetryMessage"
                    />
                    <Contact 
                        v-if="displayContactInfo && !displayTemplate" 
                        class="bg-white h-full" 
                        :fields="props.fields" 
                        :contact="contact" 
                        :locationSettings="props.locationSettings" 
                    />
                </div>
                <div v-if="contact && !displayContactInfo && !formLoading && !displayTemplate" class="w-full py-4">
                    <ChatForm 
                        :contact="contact" 
                        :simpleForm="simpleForm" 
                        :chatLimitReached="isChatLimitReached" 
                        @viewTemplate="displayTemplate = true;" 
                        @optimisticMessageSent="handleOptimisticMessage"
                        @messageSent="handleMessageSent"
                    />
                </div>
                <div v-if="displayTemplate" class="flex-1 overflow-y-hidden">
                    <CampaignForm 
                        v-if="displayTemplate" 
                        class="bg-white h-full" 
                        :contact="contact.uuid" 
                        :templates="templates" 
                        :contactGroups="[]" 
                        :settings="props.settings" 
                        :displayCancelBtn="false" 
                        :displayTitle="true" 
                        :isCampaignFlow="false" 
                        :scheduleTemplate="false" 
                        :sendText="'Send Message'" 
                        @viewTemplate="displayTemplate = false;"
                    />
                </div>
            </div>
            <!--<div v-if="contact" class="md:w-[25%] min-w-0 bg-cover flex flex-col bg-white border-l">
                <ChatContact v-if="contact" class="bg-white h-full" :contact="contact" />
            </div>-->
        </div>
        <button class="hidden" ref="toggleNavbarBtn" @click="slotProps.toggleNavBar"></button>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { default as axios } from 'axios';
    import { router, useForm } from '@inertiajs/vue3';
    import { defineEmits, ref, shallowRef, onMounted, watch } from 'vue';
    import CampaignForm from '@/Components/CampaignForm.vue';
    import ChatForm from '@/Components/ChatComponents/ChatForm.vue';
    import ChatHeader from '@/Components/ChatComponents/ChatHeader.vue';
    import ChatTable from '@/Components/ChatComponents/ChatTable.vue';
    import ChatThread from '@/Components/ChatComponents/ChatThread.vue';
    import ChatContact from '@/Components/ChatComponents/ChatContact.vue';
    import Contact from '@/Components/ContactInfo.vue';
    import { getEchoInstance } from '../../../echo';

    const props = defineProps({
        rows: Object,
        rowCount: Number,
        pusherSettings: Object,
        workspaceId: Number,
        isChatLimitReached: Boolean,
        toggleNavBar: Function,
        state: String,
        demoNumber: String,
        settings: Object,
        status: String,
        chatThread: Array,
        hasMoreMessages: Boolean,
        nextPage: Number,
        addon: Object,
        contact: Object,
        ticket: Object,
        chat_sort_direction: String,
        filters: Object,
        templates: Array,
        fields: Array,
        locationSettings: Object,
        simpleForm: Boolean
    });

    const rows = ref(props.rows);
    const rowCount = ref(props.rowCount);
    const scrollContainer2 = ref(null);
    const loadingThread = ref(false);
    const displayContactInfo = ref(false);
    const displayTemplate = ref(false);
    const formLoading = ref(false);
    const isChatLimitReached = ref(props.isChatLimitReached);
    const toggleNavbarBtn = ref(null);
    const config = ref(props.settings?.metadata ?? null);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const ticketingIsEnabled = ref(settings.value?.tickets?.active ?? false);
    const chatThread = shallowRef(props.chatThread); // Optimized: shallow reactivity for large arrays
    const contact = ref(props.contact);
    const chatThreadRef = ref(null);
    
    // Cache untuk menyimpan data chat yang sudah di-load
    const chatCache = new Map();
    let lastFetchTime = 0;
    const DEBOUNCE_DELAY = 150; // ms

    watch(() => props.rows, (newRows) => {
        rows.value = newRows;
    });
    
    watch(() => props.contact, (newContact) => {
        if (newContact) {
            contact.value = newContact;
            chatThread.value = props.chatThread || [];
        }
    });

    watch(() => props.chatThread, (newThread) => {
        if (newThread) {
            chatThread.value = newThread;
        }
    });

    const toggleDropdown = () => {
        isOpen.value = !isOpen.value;
    }

    function toggleContactView(value) {
        displayContactInfo.value = value;
    }

    const scrollToBottom = () => {
        const container = scrollContainer2.value;
        if (container) {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth',
            });
        }
    };

    const closeThread = () => {
        toggleNavbarBtn.value.click();
        contact.value = null;
    }

    const deleteThread = async () => {
        chatThread.value = [];
        await axios.delete('/chats/' + contact.value.uuid);
    }

    // Handle contact selection without page reload (SPA behavior like WhatsApp Web)
    const selectContact = async (selectedContact) => {
        // Debounce: Prevent rapid consecutive requests
        const now = Date.now();
        if (now - lastFetchTime < DEBOUNCE_DELAY) {
            console.log('⏱️ Request debounced');
            return;
        }
        lastFetchTime = now;
        
        // INSTANT FEEDBACK: Update contact immediately (optimistic)
        contact.value = selectedContact;
        loadingThread.value = true;
        
        // Check cache first for instant load
        const cacheKey = `chat_${selectedContact.uuid}`;
        if (chatCache.has(cacheKey)) {
            console.log('💾 Loading from cache:', selectedContact.name);
            const cachedData = chatCache.get(cacheKey);
            
            // IMPORTANT: Create new array reference for shallowRef to detect change
            chatThread.value = [...cachedData.chatThread];
            loadingThread.value = false;
            
            // Fetch fresh data in background to update cache
            fetchChatDataInBackground(selectedContact.uuid, cacheKey);
            return;
        }
        
        try {
            // Fetch chat thread for selected contact (tanpa update URL)
            const response = await axios.get(`/chats/${selectedContact.uuid}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.data) {
                // Update contact and chat thread without page reload
                contact.value = response.data.contact || selectedContact;
                
                // IMPORTANT: Create new array reference for shallowRef to detect change
                chatThread.value = [...(response.data.chatThread || [])];
                
                // CRITICAL: Update rows to reflect zero unread count for this contact
                updateContactInSidebar(response.data.contact);
                
                // Store in cache for future instant access
                chatCache.set(cacheKey, {
                    chatThread: response.data.chatThread || [],
                    timestamp: Date.now()
                });
                
                // Limit cache size to prevent memory bloat (keep last 20 chats)
                if (chatCache.size > 20) {
                    const firstKey = chatCache.keys().next().value;
                    chatCache.delete(firstKey);
                }
                
                console.log('✅ Contact switched & cached:', selectedContact.name);
                
                // Close mobile sidebar if open
                if (window.innerWidth < 768) {
                    toggleNavbarBtn.value?.click();
                }
                
                // Scroll to bottom after content loads
                setTimeout(scrollToBottom, 100);
            }
        } catch (error) {
            console.error('Error loading chat:', error);
            // Revert optimistic update on error
            contact.value = props.contact;
        } finally {
            loadingThread.value = false;
        }
    }
    
    // Background fetch untuk update cache tanpa blocking UI
    const fetchChatDataInBackground = async (uuid, cacheKey) => {
        try {
            const response = await axios.get(`/chats/${uuid}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.data && response.data.chatThread) {
                // Update cache silently
                chatCache.set(cacheKey, {
                    chatThread: response.data.chatThread,
                    timestamp: Date.now()
                });
                
                // If user still viewing this contact, update the UI too
                if (contact.value && contact.value.uuid === uuid) {
                    chatThread.value = [...response.data.chatThread];
                    console.log('🔄 Cache & UI refreshed in background');
                } else {
                    console.log('🔄 Cache refreshed in background');
                }
            }
        } catch (error) {
            console.error('Background fetch error:', error);
        }
    }
    
    // Prefetch contact data untuk instant loading
    const prefetchContactData = async (uuid) => {
        const cacheKey = `chat_${uuid}`;
        
        // Skip if already cached
        if (chatCache.has(cacheKey)) {
            return;
        }
        
        try {
            const response = await axios.get(`/chats/${uuid}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.data && response.data.chatThread) {
                chatCache.set(cacheKey, {
                    chatThread: response.data.chatThread,
                    timestamp: Date.now()
                });
                console.log('🚀 Prefetched contact:', uuid);
            }
        } catch (error) {
            // Silent fail for prefetch
            console.debug('Prefetch skipped:', uuid);
        }
    }

    const updateChatThread = (chat) => {
        const wamId = chat[0].value.wam_id;
        const wamIdExists = chatThread.value.some(existingChat => existingChat[0].value.wam_id === wamId);

        if (!wamIdExists && chat[0].value.deleted_at == null) {
            chatThread.value.push(chat);
            setTimeout(scrollToBottom, 100);
        }
    }
    
    // Update contact in sidebar (reset unread count)
    const updateContactInSidebar = (updatedContact) => {
        if (!rows.value?.data) return;
        
        const index = rows.value.data.findIndex(c => c.id === updatedContact.id);
        if (index !== -1) {
            // Update the contact with fresh data (including unread_messages = 0)
            rows.value.data[index] = {
                ...rows.value.data[index],
                ...updatedContact,
                unread_messages: 0 // Force zero unread
            };
            
            console.log('📭 Sidebar updated: unread reset for', updatedContact.full_name || updatedContact.phone);
        }
    }
    
    // Handle optimistic message updates from ChatForm
    const handleOptimisticMessage = (optimisticMessage) => {
        console.log('📤 Optimistic message received in Index:', optimisticMessage);
        
        // Pass to ChatThread component for instant display
        if (chatThreadRef.value) {
            chatThreadRef.value.handleOptimisticMessageSent(optimisticMessage);
        }
    }
    
    // Handle message send confirmation
    const handleMessageSent = (message) => {
        console.log('✅ Message confirmed sent:', message);
        
        // Update chat list to show latest message
        refreshSidePanel();
    }
    
    // Handle retry failed message
    const handleRetryMessage = (failedMessage) => {
        console.log('🔄 Retrying message:', failedMessage);
        // Will be handled by ChatForm
    }

    const updateSidePanel = async(chat) => {
        if(contact.value && contact.value.id == chat[0].value.contact_id){
            updateChatThread(chat);
            
            // ENHANCED: Pass new message to ChatThread for real-time display
            if (chatThreadRef.value && chatThreadRef.value.addNewMessage) {
                chatThreadRef.value.addNewMessage(chat[0].value);
            }
        }

        try {
            const response = await axios.get('/chats');
            if (response?.data?.result) {
                rows.value = response.data.result;
            }
        } catch (error) {
            console.error('Error updating side panel:', error);
        }
    }

    const onCloseDemoModal = () => {
        isDemoModalOpen.value = false;
    }

    onMounted(() => {
        const echo = getEchoInstance(
            props.pusherSettings['pusher_app_key'],
            props.pusherSettings['pusher_app_cluster']
        );

        echo.channel('chats.ch' + props.workspaceId)
            .listen('NewChatEvent', (event) => {
                // ENHANCED: Support for group chats (TASK-FE-3)
                console.log('New chat received:', event);

                // Determine if private or group chat
                const isGroup = event.chat?.chat_type === 'group';

                if (isGroup) {
                    // For group chats, event.group contains group info
                    console.log('Group chat received:', event.group);

                    // Update chat thread if user is viewing this group
                    if (contact.value && contact.value.group_id === event.group?.id) {
                        updateChatThread(event.chat);
                    }
                } else {
                    // For private chats, event.contact contains contact info
                    updateSidePanel(event.chat);
                }

                // Always refresh side panel to show new chat in list
                refreshSidePanel();
            });

        scrollToBottom();
        
        // PREFETCH: Load first 3 contacts in background for instant switching
        if (props.rows?.data && Array.isArray(props.rows.data)) {
            setTimeout(() => {
                const topContacts = props.rows.data.slice(0, 3);
                topContacts.forEach((contact, index) => {
                    setTimeout(() => {
                        prefetchContactData(contact.uuid);
                    }, index * 300); // Stagger requests to avoid overwhelming server
                });
            }, 1000); // Wait 1s after page load to avoid blocking initial render
        }
    });

    // NEW: Refresh side panel (for group chats support)
    const refreshSidePanel = async () => {
        try {
            const response = await axios.get('/chats');
            if (response?.data?.result) {
                rows.value = response.data.result;
            }
        } catch (error) {
            console.error('Error refreshing side panel:', error);
        }
    }
</script>