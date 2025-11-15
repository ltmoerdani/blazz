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
    import { defineEmits, ref, onMounted, watch } from 'vue';
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
    const chatThread = ref(props.chatThread);
    const contact = ref(props.contact);
    const chatThreadRef = ref(null);

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
        loadingThread.value = true;
        
        // Update URL without reload using History API
        const newUrl = `/chats/${selectedContact.uuid}`;
        window.history.pushState({ contactId: selectedContact.id }, '', newUrl);
        
        try {
            // Fetch chat thread for selected contact
            const response = await axios.get(`/chats/${selectedContact.uuid}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.data) {
                // Update contact and chat thread without page reload
                contact.value = response.data.contact || selectedContact;
                chatThread.value = response.data.chatThread || [];
                
                // Close mobile sidebar if open
                if (window.innerWidth < 768) {
                    toggleNavbarBtn.value?.click();
                }
                
                // Scroll to bottom after content loads
                setTimeout(scrollToBottom, 100);
            }
        } catch (error) {
            console.error('Error loading chat:', error);
        } finally {
            loadingThread.value = false;
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