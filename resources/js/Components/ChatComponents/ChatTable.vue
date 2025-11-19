<script setup>
    import axios from 'axios';
    import { ref, watch, onMounted, onUnmounted } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router } from "@inertiajs/vue3";
    import TicketStatusToggle from '@/Components/TicketStatusToggle.vue';
    import SortDirectionToggle from '@/Components/SortDirectionToggle.vue';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        },
        rowCount: {
            type: Number,
            required: true,
        },
        ticketingIsEnabled: {
            type: Boolean
        },
        status: {
            type: String,
        },
        chatSortDirection: {
            type: String
        },
        // NEW: WhatsApp accounts for filter dropdown (TASK-FE-1)
        accounts: {
            type: Array,
            default: () => []
        }
    });

    const isSearching = ref(false);
    const selectedContact = ref(null);
    const isLoadingMore = ref(false);
    const hasNextPage = ref(true);
    const currentPage = ref(1);
    const scrollContainer = ref(null);
    const loadMoreTrigger = ref(null);
    const localRows = ref([...props.rows.data]); // Local copy for manipulation
    const emit = defineEmits(['view', 'contact-selected', 'update-rows']);

    function viewChat(contact) {
        selectedContact.value = contact;
        emit('view', contact);
    }
    
    // NEW: Handle contact selection without page reload (SPA behavior)
    function selectContact(contact, event) {
        event.preventDefault(); // Prevent default link behavior
        
        // Emit FIRST so parent sees original state (unread > 0)
        emit('contact-selected', contact);
        
        selectedContact.value = contact;
        
        // OPTIMISTIC UPDATE: Mark as read immediately in localRows
        const index = localRows.value.findIndex(c => c.id === contact.id);
        if (index !== -1) {
             localRows.value[index].unread_messages = 0;
        }
    }

    const contentType = (metadata) => {
        // Handle both string JSON and object (after Laravel cast)
        const chatData = typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
        return chatData.type;
    }

    const content = (metadata) => {
        // Handle both string JSON and object (after Laravel cast)
        return typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
    }

    const getExtension = (fileFormat) => {
        const formatMap = {
            'text/plain': 'TXT',
            'application/pdf': 'PDF',
            'application/vnd.ms-powerpoint': 'PPT',
            'application/msword': 'DOC',
            'application/vnd.ms-excel': 'XLS',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PPTX',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
        };

        return formatMap[fileFormat] || 'Unknown';
    };

    const getContactDisplayName = (metadata) => {
        // Handle both string JSON and object (after Laravel cast)
        const chatData = typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
        const contacts = chatData.contacts;
        if (contacts.length === 1) {
            const contact = contacts[0];
            return contact.name.formatted_name || `${contact.name.first_name} ${contact.name.last_name}`;
        } else if (contacts.length > 1) {
            const firstName = contacts[0].name.first_name;
            const otherContactsCount = contacts.length - 1;
            return `${firstName} and ${otherContactsCount} other contacts`;
        } else {
            return 'No contacts available';
        }
    }

    const formatTime = (time) => {
        if(!time){
            return "Invalid time";
        } else {
            const currentTime = new Date();
            const targetTime = new Date(time);
            const timeDiff = currentTime - targetTime;

            // Check if the time is today
            if (
                targetTime.getDate() === currentTime.getDate() &&
                targetTime.getMonth() === currentTime.getMonth() &&
                targetTime.getFullYear() === currentTime.getFullYear()
            ) {
                return formatDate(targetTime, 'h:mm a');
            }

            // Check if the time is yesterday
            const yesterday = new Date();
            yesterday.setDate(currentTime.getDate() - 1);
            if (
                targetTime.getDate() === yesterday.getDate() &&
                targetTime.getMonth() === yesterday.getMonth() &&
                targetTime.getFullYear() === yesterday.getFullYear()
            ) {
                return 'Yesterday';
            }

            // If beyond yesterday, return the time in the format d/m/y
            return formatDate(targetTime, 'd/m/y');
        }
    };

    const formatDate = (date, format) => {
        let options = null;
        if(format === 'h:mm a'){
            options = { hour12: true, hour: 'numeric', minute: 'numeric' };
        } else {
            options = { day: 'numeric', month: 'numeric', year: 'numeric' };
        }
        
        return new Intl.DateTimeFormat('en-US', options).format(date);
    };

    const queryParamsString = window.location.search;

    // Watch for changes in the query parameters
    watch(() => queryParamsString,
        (newQuery, oldQuery) => {
            console.log('Query parameters changed:', newQuery);
            // Perform actions based on query parameter changes
        }
    );

    const params = ref({
        search: props.filters.search,
        account_id: props.filters?.account_id || '', // NEW: Track account filter
    });

    // NEW: Account filter state (TASK-FE-1)
    const selectedAccountId = ref(props.filters?.account_id || '');

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        const url = window.location.pathname;

        router.visit(url, {
            method: 'get',
            data: params.value,
        })
    }

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    // NEW: Filter by WhatsApp account (TASK-FE-1)
    const filterByAccount = () => {
        params.value.account_id = selectedAccountId.value;
        runSearch();
    }

    // NEW: Format phone number for display (TASK-FE-1)
    const formatPhone = (phone) => {
        if (!phone) return '';
        // Format: +62 812-3456-7890
        return phone.replace(/(\+\d{2})(\d{3})(\d{4})(\d+)/, '$1 $2-$3-$4');
    }
    
    // Infinite Scroll Implementation
    let intersectionObserver = null;
    
    const loadMoreContacts = async () => {
        if (isLoadingMore.value || !hasNextPage.value) {
            console.log('🚫 Load blocked:', { isLoading: isLoadingMore.value, hasNext: hasNextPage.value });
            return;
        }
        
        console.log('📥 Starting load more...', { currentPage: currentPage.value });
        isLoadingMore.value = true;
        const nextPage = currentPage.value + 1;
        
        try {
            const url = new URL(window.location.pathname, window.location.origin);
            url.searchParams.set('page', nextPage);
            
            // Preserve existing filters
            if (params.value.search) {
                url.searchParams.set('search', params.value.search);
            }
            if (params.value.account_id) {
                url.searchParams.set('account_id', params.value.account_id);
            }
            
            const response = await axios.get(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.data?.result?.data) {
                const newContacts = response.data.result.data;
                
                console.log('📦 Received data:', {
                    newCount: newContacts.length,
                    hasMorePages: response.data.result.meta?.has_more_pages
                });
                
                // IMPORTANT: Only append if we got new data
                if (newContacts.length > 0) {
                    // Append to local copy
                    localRows.value.push(...newContacts);
                    
                    // Update pagination state
                    currentPage.value = nextPage;
                    
                    console.log('✅ Loaded more contacts:', {
                        newContacts: newContacts.length,
                        totalNow: localRows.value.length,
                        currentPage: currentPage.value
                    });
                }
                
                // CRITICAL: Use backend's has_more_pages if available, otherwise check length
                if (response.data.result.meta?.has_more_pages !== undefined) {
                    hasNextPage.value = response.data.result.meta.has_more_pages;
                } else {
                    // Fallback: if we got less than perPage, no more data
                    hasNextPage.value = newContacts.length >= 15;
                }
                
                console.log('📊 Pagination state:', {
                    hasNextPage: hasNextPage.value,
                    currentPage: currentPage.value
                });
            } else {
                // No data received, stop pagination
                hasNextPage.value = false;
                console.log('⚠️ No data in response, stopping pagination');
            }
        } catch (error) {
            console.error('❌ Error loading more contacts:', error);
            hasNextPage.value = false; // Stop trying on error
        } finally {
            isLoadingMore.value = false;
        }
    };
    
    const handleScroll = debounce(() => {
        if (!scrollContainer.value) return;
        
        const container = scrollContainer.value;
        const scrollPosition = container.scrollTop + container.clientHeight;
        const scrollHeight = container.scrollHeight;
        
        // Trigger load when user scrolls to 80% of the content
        if (scrollPosition >= scrollHeight * 0.8 && !isLoadingMore.value && hasNextPage.value) {
            loadMoreContacts();
        }
    }, 100);
    
    // Setup Intersection Observer for more efficient infinite scroll
    const setupIntersectionObserver = () => {
        if (!loadMoreTrigger.value) {
            console.log('⚠️ loadMoreTrigger not available yet');
            return;
        }
        
        const options = {
            root: scrollContainer.value,
            rootMargin: '50px', // Reduced from 100px to prevent premature trigger
            threshold: 0.01 // Very small threshold
        };
        
        intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                console.log('👁️ Intersection:', {
                    isIntersecting: entry.isIntersecting,
                    isLoading: isLoadingMore.value,
                    hasNext: hasNextPage.value
                });
                
                if (entry.isIntersecting && !isLoadingMore.value && hasNextPage.value) {
                    console.log('🎯 Triggering load from Intersection Observer');
                    loadMoreContacts();
                }
            });
        }, options);
        
        intersectionObserver.observe(loadMoreTrigger.value);
        console.log('✅ Intersection Observer setup complete');
    };
    
    // Watch for props changes to update local copy
    watch(() => props.rows.data, (newData) => {
        if (newData) {
            localRows.value = [...newData];
            console.log('🔄 Rows updated from props:', localRows.value.length);
        }
    }, { deep: false });
    
    // Reset pagination when search or filter changes
    watch([() => params.value.search, () => params.value.account_id], () => {
        console.log('🔍 Filter changed, resetting pagination');
        currentPage.value = 1;
        hasNextPage.value = true;
        localRows.value = [...props.rows.data]; // Reset to initial data
    });
    
    // NEW: Method to programmatically move a contact to the top (called by parent)
    const moveContactToTop = (contactId, updateData = {}) => {
        console.log('🔄 ChatTable: Moving contact to top', contactId);
        
        const index = localRows.value.findIndex(c => c.id == contactId);
        if (index !== -1) {
            // Create copy of contact
            const contact = { ...localRows.value[index] };
            
            // Apply updates (last message, time, unread count)
            Object.assign(contact, updateData);
            
            // Remove from current position
            localRows.value.splice(index, 1);
            
            // Add to top
            localRows.value.unshift(contact);
            
            // Scroll to top to ensure visibility
            if (scrollContainer.value) {
                scrollContainer.value.scrollTop = 0;
            }
            
            console.log('✅ ChatTable: Contact moved to top successfully');
        } else {
            console.warn('⚠️ ChatTable: Contact not found for reordering', contactId);
        }
    };

    // Expose method to parent
    defineExpose({
        moveContactToTop
    });

    onMounted(() => {
        // Initialize local rows from props
        localRows.value = [...props.rows.data];
        
        // Initialize pagination state from props
        if (props.rows?.meta) {
            currentPage.value = props.rows.meta.current_page || 1;
            
            // Check has_more_pages from backend if available
            if (props.rows.meta.has_more_pages !== undefined) {
                hasNextPage.value = props.rows.meta.has_more_pages;
            } else {
                // Fallback: check if we have full page of data
                hasNextPage.value = props.rows.data.length >= 15;
            }
            
            console.log('📋 Initial state:', {
                currentPage: currentPage.value,
                hasNextPage: hasNextPage.value,
                contactCount: localRows.value.length
            });
        }
        
        // Setup Intersection Observer with slight delay to ensure DOM is ready
        setTimeout(() => {
            setupIntersectionObserver();
        }, 500);
    });
    
    onUnmounted(() => {
        if (intersectionObserver) {
            intersectionObserver.disconnect();
        }
    });
</script>
<template>
    <div class="px-4 py-4 border-b">
        <div class="flex items-center justify-between space-x-1 text-xl">
            <div class="flex space-x-1">
                <h2>{{ $t('Chats') }}</h2>
                <span class="text-slate-500">{{ rowCount }}</span>
            </div>
        </div>
        <div class="bg-slate-50 rounded-md mt-3 flex items-center py-[2px]">
            <div class="pl-3 py-2">
                <svg class="text-slate-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0-14 0m18 11l-6-6"/></svg>
            </div>
            <input @input="search" v-model="params.search" class="w-full bg-slate-50 outline-none rounded-xl py-2 pl-2 mr-2 text-sm" type="text" :placeholder="$t('Search name or number...')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="pr-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="pr-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>

        <!-- NEW: Account Filter Dropdown (TASK-FE-1) -->
        <div v-if="accounts && accounts.length > 0" class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ $t('Filter by WhatsApp Number') }}
            </label>
            <select
                v-model="selectedAccountId"
                @change="filterByAccount"
                class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">{{ $t('All Conversations') }}</option>
                <option v-for="account in accounts" :key="account.id" :value="account.id">
                    {{ formatPhone(account.phone_number) }}
                    <template v-if="account.provider_type === 'webjs'"> (WhatsApp Web.js)</template>
                    <template v-if="account.unread_count > 0"> ({{ account.unread_count }} unread)</template>
                </option>
            </select>
        </div>
        <div v-if="ticketingIsEnabled" class="grid grid-cols-2 mt-4 items-center w-full">
            <TicketStatusToggle :status="status" :rowCount="rowCount"/>
            <div class="flex ml-auto gap-x-1">
                <!--<span class="cursor-pointer hover:bg-slate-50 p-1 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 5.6c0-.56 0-.84-.11-1.054a.998.998 0 0 0-.436-.437C19.24 4 18.96 4 18.4 4H5.6c-.56 0-.84 0-1.054.109a1 1 0 0 0-.437.437C4 4.76 4 5.04 4 5.6v.737c0 .245 0 .367.028.482a1 1 0 0 0 .12.29c.061.1.148.187.32.36l5.063 5.062c.173.173.26.26.321.36c.055.09.096.188.12.29c.028.114.028.235.028.474v4.756c0 .857 0 1.286.18 1.544a1 1 0 0 0 .674.416c.311.046.695-.145 1.461-.529l.8-.4c.322-.16.482-.24.599-.36a1 1 0 0 0 .231-.374c.055-.158.055-.338.055-.697v-4.348c0-.245 0-.367.028-.482a.998.998 0 0 1 .12-.29c.06-.1.147-.186.317-.356l.004-.004l5.063-5.062c.172-.173.258-.26.32-.36a.994.994 0 0 0 .12-.29C20 6.706 20 6.584 20 6.345z"/></svg>
                </span>-->
                <SortDirectionToggle :direction="props.chatSortDirection" :url="'/chats/update-sort-direction'"/>
            </div>
        </div>
    </div>
    <div class="flex-grow overflow-y-auto h-[65vh]" ref="scrollContainer" @scroll="handleScroll">
        <!-- Changed from Link to div for SPA navigation -->
        <div 
            @click="selectContact(contact, $event)" 
            class="block border-b group-hover:pr-0 cursor-pointer" 
            :class="[contact.unread_messages > 0 ? 'bg-green-50' : '', selectedContact?.id === contact.id ? 'bg-blue-50 border-l-4 border-l-blue-500' : '']" 
            v-for="(contact, index) in localRows" 
            :key="contact.id || index">
            <div class="flex space-x-2 hover:bg-gray-50 py-3 px-4">
                <!-- NEW: Chat Type Icon (TASK-FE-2) -->
                <div class="w-[15%] relative">
                    <!-- Group Chat Icon -->
                    <div v-if="contact.type === 'group'" class="rounded-full w-10 h-10 flex items-center justify-center bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                    </div>
                    <!-- Private Chat Icon -->
                    <template v-else>
                        <img v-if="contact.avatar" class="rounded-full w-10 h-10" :src="contact.avatar">
                        <div v-else class="rounded-full w-10 h-10 flex items-center justify-center bg-slate-200 capitalize">{{ contact.full_name.substring(0, 1) }}</div>
                    </template>
                </div>
                <div class="w-[85%]">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0">
                            <!-- Contact/Group Name -->
                            <h3 class="truncate font-semibold">
                                {{ contact.type === 'group' ? (contact.first_name || contact.phone) : contact.full_name }}
                                <!-- NEW: Participant count for groups (TASK-FE-2) -->
                                <span v-if="contact.type === 'group' && contact.group_metadata?.participants" class="text-xs text-gray-500 font-normal ml-1">
                                    ({{ contact.group_metadata.participants.length }} members)
                                </span>
                            </h3>
                            <!-- NEW: Provider Type Badge (TASK-FE-2) -->
                            <div class="flex items-center gap-1 mt-1">
                                <span v-if="contact.provider_type === 'webjs'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    WhatsApp Web.js
                                </span>
                                <span v-else-if="contact.provider_type === 'meta'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    Meta API
                                </span>
                            </div>
                        </div>
                        <span class="self-center text-slate-500 text-xs ml-2">{{ formatTime(contact?.last_chat?.created_at) }}</span>
                    </div>
                    <div v-if="contact?.last_chat?.deleted_at === null" class="flex justify-between">
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='text'" class="text-slate-500 text-xs truncate self-end">
                            <!-- NEW: Show sender name for group messages (TASK-FE-2) -->
                            <span v-if="contact.type === 'group' && contact.last_sender_name" class="font-medium text-gray-700">
                                {{ contact.last_sender_name }}:
                            </span>
                            {{ content(contact?.last_chat?.metadata).text.body }}
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='button'" class="text-slate-500 text-xs truncate self-end"> {{ content(contact?.last_chat?.metadata).button.text }}</div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='interactive'" class="text-slate-500 text-xs truncate self-end"> {{ content(contact?.last_chat?.metadata).interactive?.button_reply?.title || content(contact?.last_chat?.metadata).interactive?.list_reply?.title }}</div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='image'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.9 13.98l2.1 2.53l3.1-3.99c.2-.26.6-.26.8.01l3.51 4.68a.5.5 0 0 1-.4.8H6.02c-.42 0-.65-.48-.39-.81L8.12 14c.19-.26.57-.27.78-.02z"/></svg>
                                <span class="ml-2">{{ $t('Photo') }}</span>
                            </div>
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='document'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M14.25 2.5a.25.25 0 0 0-.25-.25H7A2.75 2.75 0 0 0 4.25 5v14A2.75 2.75 0 0 0 7 21.75h10A2.75 2.75 0 0 0 19.75 19V9.147a.25.25 0 0 0-.25-.25H15a.75.75 0 0 1-.75-.75V2.5Zm.75 9.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1 0-1.5h6Zm0 4a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1 0-1.5h6Z" clip-rule="evenodd"/><path fill="currentColor" d="M15.75 2.824c0-.184.193-.301.336-.186c.121.098.23.212.323.342l3.013 4.197c.068.096-.006.22-.124.22H16a.25.25 0 0 1-.25-.25V2.824Z"/></svg>
                                <span class="ml-2">{{ getExtension(contact?.last_chat?.media.type) }} {{ $t('File') }}</span>
                            </div>
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='video'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" d="M3.5 2.5A2.5 2.5 0 0 0 1 5v6a2.5 2.5 0 0 0 2.5 2.5h5A2.5 2.5 0 0 0 11 11V5a2.5 2.5 0 0 0-2.5-2.5h-5Zm10.684 1.61L12 5.893v4.215l2.184 1.78a.5.5 0 0 0 .816-.389v-7a.5.5 0 0 0-.816-.387Z"/></svg>
                                <span class="ml-2">{{ $t('Video') }}</span>
                            </div>
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='audio'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512"><path fill="currentColor" d="M256 80C149.9 80 62.4 159.4 49.6 262c9.4-3.8 19.6-6 30.4-6c26.5 0 48 21.5 48 48v128c0 26.5-21.5 48-48 48c-44.2 0-80-35.8-80-80V288C0 146.6 114.6 32 256 32s256 114.6 256 256v112c0 44.2-35.8 80-80 80c-26.5 0-48-21.5-48-48V304c0-26.5 21.5-48 48-48c10.8 0 21 2.1 30.4 6C449.6 159.4 362.1 80 256 80z"/></svg>
                                <span class="ml-2">{{ $t('Audio') }}</span>
                            </div>
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='sticker'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path fill="currentColor" d="M168 32H88a56.06 56.06 0 0 0-56 56v80a56.06 56.06 0 0 0 56 56h48a8.07 8.07 0 0 0 2.53-.41c26.23-8.75 76.31-58.83 85.06-85.06A8.07 8.07 0 0 0 224 136V88a56.06 56.06 0 0 0-56-56Zm-32 175.42V176a40 40 0 0 1 40-40h31.42c-9.26 21.55-49.87 62.16-71.42 71.42Z"/></svg>
                                <span class="ml-2">{{ $t('Sticker') }}</span>
                            </div>
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='contacts'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" d="M3 14s-1 0-1-1s1-4 6-4s6 3 6 4s-1 1-1 1H3Zm5-6a3 3 0 1 0 0-6a3 3 0 0 0 0 6Z"/></svg>
                                <span class="ml-2">
                                    {{ getContactDisplayName(contact?.last_chat?.metadata) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="contentType(contact?.last_chat?.metadata) ==='location'" class="text-slate-500 text-xs truncate self-end"> 
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" d="M9.156 14.544C10.899 13.01 14 9.876 14 7A6 6 0 0 0 2 7c0 2.876 3.1 6.01 4.844 7.544a1.736 1.736 0 0 0 2.312 0ZM6 7a2 2 0 1 1 4 0a2 2 0 0 1-4 0Z"/></svg>
                                <span class="ml-2">{{ $t('Location') }}</span>
                            </div>
                        </div>
                        <span v-if="contact.unread_messages > 0" class="bg-green-600 text-white rounded-md py-[1px] px-[8px] min-w-10 text-[10px] flex items-center justify-center">{{ contact.unread_messages }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Intersection Observer Target - Must be AFTER all items -->
        <div v-if="hasNextPage && !isLoadingMore" ref="loadMoreTrigger" class="h-4"></div>
        
        <!-- Infinite Scroll Loading Indicator -->
        <div v-if="isLoadingMore" class="py-4 flex justify-center items-center">
            <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="ml-2 text-sm text-gray-500">Loading more chats...</span>
        </div>
        
        <!-- End of List Indicator -->
        <div v-if="!hasNextPage && localRows.length > 0" class="py-4 text-center text-sm text-gray-400">
            <p>You've reached the end of your chats</p>
            <p class="text-xs mt-1">Showing {{ localRows.length }} conversations</p>
        </div>
    </div>
</template>
  