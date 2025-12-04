<template>
    <div :class="rtlClass">
        <MobileSidebar :user="user" :config="config" :workspace="workspace" :workspaces="workspaces" :title="currentPageTitle" :displayCreateBtn="displayCreateBtn" :displayTopBar="viewTopBar"></MobileSidebar>

        <div class="md:mt-0 md:pt-0 flex md:h-screen w-full tracking-[0.3px] bg-gray-300/10" :class="viewTopBar === false ? 'mt-0 pt-0' : ''">
            <Sidebar :user="user" :config="config" :workspace="workspace" :workspaces="workspaces" :unreadMessages="unreadMessages"></Sidebar>
            <div class="md:h-screen flex flex-col w-full min-w-0 overflow-hidden">
                <slot :user="user" :toggleNavBar="toggleTopBar" @testEmit="doSomething"></slot>
            </div>
        </div>

        <audio ref="audioPlayer" allow="autoplay"></audio>
    </div>
</template>
<script setup>
    import { usePage } from "@inertiajs/vue3";
    import Sidebar from "./Sidebar.vue";
    import { defineProps, ref, computed, watch, onMounted } from 'vue';
    import { toast } from 'vue3-toastify';
    import MobileSidebar from "./MobileSidebar.vue";
    import 'vue3-toastify/dist/index.css';
    import { getEchoInstance } from '../../../echo';
    import { useRtl } from '@/composables/useRtl';

    const { rtlClass, isRtl } = useRtl();

    const viewTopBar = ref(true);
    const user = computed(() => usePage().props.auth.user);
    const config = computed(() => usePage().props.config);
    const workspace = computed(() => usePage().props.workspace);
    const workspaces = computed(() => usePage().props.workspaces);
    const currentPageTitle = computed(() => usePage().props.title);
    const displayCreateBtn = computed(() => usePage().props.allowCreate);
    const unreadMessages = ref(usePage().props.unreadMessages);
    const audioPlayer = ref(null);

    watch(() => [usePage().props.flash, { deep: true }], () => {
        if(usePage().props.flash.status != null){
            toast(usePage().props.flash.status.message, {
                autoClose: 3000,
            });
        }
    });

    const toggleTopBar = () => {
        viewTopBar.value = !viewTopBar.value;
    };

    const getValueByKey = (key) => {
        const found = config.value.find(item => item.key === key);
        return found ? found.value : '';
    };

    const setupSound = () => {
        const settings = workspace.value.metadata ? JSON.parse(workspace.value.metadata) : {};
        const notifications = settings.notifications || {};

        if (notifications?.enable_sound && audioPlayer.value) {
            audioPlayer.value.src = notifications?.tone;
            audioPlayer.value.volume = notifications?.volume || 1.0;
        }
    };

    const playSound = () => {
        if (audioPlayer.value) {
            audioPlayer.value.play().catch((error) => {
                console.warn("Audio playback failed:", error);
            });
        }
    };

    onMounted(() => {
        setupSound();

        // Listen for new messages globally (untuk badge counter)
        // Following riset pattern: private workspace channel
        const workspaceChannel = window.Echo.private(`workspace.${workspace.value.id}`);
        console.log('📡 [App.vue] Subscribing to PRIVATE workspace channel:', `workspace.${workspace.value.id}`);

        workspaceChannel.listen('.message.received', (event) => {
            console.log('🔔 [App.vue] New message received globally:', event);

            // Emit custom DOM event for chat page to catch
            window.dispatchEvent(new CustomEvent('new-chat-message', { 
                detail: event 
            }));
            console.log('📢 [App.vue] Dispatched new-chat-message event');

            // Only increment global counter if user is NOT on chat page
            // This prevents double counting since Index.vue handles it when user is on chat page
            const isOnChatPage = window.location.pathname.includes('/chats');
            console.log('🔍 [App.vue] Path check:', { 
                pathname: window.location.pathname, 
                isOnChatPage 
            });
            
            if (!isOnChatPage) {
                console.log('➕ [App.vue] User not on chat page, incrementing global counter');
                // Increment unread messages counter
                unreadMessages.value = (unreadMessages.value || 0) + 1;
                console.log('✅ Global unread counter updated:', unreadMessages.value);
            } else {
                console.log('⏭️ [App.vue] User on chat page, App.vue delegating to Index.vue via custom event');
            }
        });

        // Listen for chat read events to update global counter (decrement)
        window.addEventListener('chat-read', () => {
            console.log('📖 [App.vue] Chat read event received');
            if (unreadMessages.value > 0) {
                unreadMessages.value--;
                console.log('✅ Global unread counter decremented:', unreadMessages.value);
            }
        });

        // Listen for chat unread events to update global counter (increment)
        // This is triggered by Index.vue when a new conversation becomes unread while on chat page
        window.addEventListener('chat-unread', () => {
            console.log('📬 [App.vue] Chat unread event received');
            unreadMessages.value = (unreadMessages.value || 0) + 1;
            console.log('✅ Global unread counter incremented:', unreadMessages.value);
        });
    });
</script>