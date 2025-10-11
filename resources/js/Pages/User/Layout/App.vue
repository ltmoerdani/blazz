<template>
    <div :class="rtlClass">
        <MobileSidebar :user="user" :config="config" :workspace="workspace" :workspaces="workspaces" :title="currentPageTitle" :displayCreateBtn="displayCreateBtn" :displayTopBar="viewTopBar"></MobileSidebar>

        <div class="md:mt-0 md:pt-0 flex md:h-screen w-full tracking-[0.3px] bg-gray-300/10" :class="viewTopBar === false ? 'mt-0 pt-0' : ''">
            <Sidebar :user="user" :config="config" :workspace="workspace" :workspaces="workspaces" :unreadMessages="unreadMessages"></Sidebar>
            <div class="md:min-h-screen flex flex-col w-full min-w-0">
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

        // Enhanced Echo initialization with dynamic broadcaster support
        const broadcastDriver = getValueByKey('broadcast_driver') || 'reverb';
        
        // Prepare configuration for both broadcasters
        const pusherConfig = {
            pusher_app_key: getValueByKey('pusher_app_key'),
            pusher_app_cluster: getValueByKey('pusher_app_cluster')
        };
        
        const reverbConfig = {
            reverb_app_key: getValueByKey('reverb_app_key'),
            reverb_host: getValueByKey('reverb_host') || '127.0.0.1',
            reverb_port: parseInt(getValueByKey('reverb_port')) || 8080,
            reverb_scheme: getValueByKey('reverb_scheme') || 'http'
        };

        console.log('App Layout: Initializing Echo with broadcaster:', broadcastDriver);

        const echo = getEchoInstance(
            pusherConfig.pusher_app_key,
            pusherConfig.pusher_app_cluster,
            broadcastDriver,
            {
                key: reverbConfig.reverb_app_key,
                host: reverbConfig.reverb_host,
                port: reverbConfig.reverb_port,
                scheme: reverbConfig.reverb_scheme
            }
        );

        // Existing chat listeners (backward compatible)
        echo.channel('chats.ch' + workspace.value.id).listen('NewChatEvent', (event) => {
            const chat = event.chat;

            if (chat[0].value.deleted_at == null && chat[0].value.type === 'inbound') {
                playSound(); // Play sound for inbound messages
                unreadMessages.value += 1; // Increment unread messages count
            }
        });

        // WhatsApp Web JS event listeners (new functionality)
        echo.channel('whatsapp.' + workspace.value.id)
            .listen('WhatsAppQRGenerated', (event) => {
                console.log('App Layout: WhatsApp QR Generated:', event);
                // This will be handled by WhatsAppSetup.vue component
            })
            .listen('WhatsAppSessionStatusChanged', (event) => {
                console.log('App Layout: WhatsApp Session Status Changed:', event);
                // This will be handled by WhatsAppSetup.vue component
            });

        console.log('App Layout: Echo setup complete with channels:', [
            'chats.ch' + workspace.value.id,
            'whatsapp.' + workspace.value.id
        ]);
    });
</script>