<script setup>
    import axios from "axios";
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
    import { ref, computed, onMounted } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import 'vue3-toastify/dist/index.css';
    import { trans } from 'laravel-vue-i18n';

    const props = defineProps({
        templates: Object,
        contactGroups: Object,
        settings: Array,
        whatsappSessions: {
            type: Array,
            default: () => []
        },
        campaignTypes: {
            type: Array,
            default: () => []
        },
        providerOptions: {
            type: Array,
            default: () => []
        },
        contact: {
            type: String,
            default: null
        },
        displayTitle: {
            type: Boolean,
            default: false
        },
        displayCancelBtn: {
            type: Boolean,
            default: true
        },
        isCampaignFlow: {
            type: Boolean,
            default: true
        },
        sendText: {
            type: String,
            default: 'Save'
        }
    });
    const isLoading = ref(false);
    const contactGroupOptions = ref([
        { value: 'all', label: trans('all contacts') },
    ]);
    const templateOptions = ref([]);
    const config = ref(props.settings?.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);

    // Check if WhatsApp is connected via either Meta API or WhatsApp Web JS
    const isWhatsAppConnected = computed(() => {
        // Check for Meta API connection
        const hasMetaApi = settings.value?.whatsapp;

        // Check for WhatsApp Web JS sessions
        const hasWebJsSessions = props.whatsappSessions && props.whatsappSessions.length > 0;

        return hasMetaApi || hasWebJsSessions;
    });

    const variableOptions = ref([
        { value: 'static', label: trans('static') },
        { value: 'dynamic', label: trans('dynamic') }
    ]);

    const dynamicOptions = ref([
        { value: 'first name', label: trans('contact first name') },
        { value: 'last name', label: trans('contact last name') },
        { value: 'name', label: trans('contact full name') },
        { value: 'phone', label: trans('contact phone') },
        { value: 'email', label: trans('contact email') },
    ]);

    // Campaign type options
    const campaignTypeOptions = ref(props.campaignTypes.length > 0 ? props.campaignTypes : [
        { value: 'template', label: trans('Use Template'), description: trans('Select an approved template from your template library') },
        { value: 'direct', label: trans('Direct Message'), description: trans('Create a custom message without using templates') }
    ]);

    // Provider options with default values
    const providerSelectOptions = ref(props.providerOptions.length > 0 ? props.providerOptions : [
        { value: 'webjs', label: 'WhatsApp Web JS', description: trans('Recommended for better compatibility and features') },
        { value: 'meta_api', label: 'Meta Business API', description: trans('Official WhatsApp Business API') }
    ]);

    const form = useForm({
        name: null,
        campaign_type: 'direct', // Default to direct message as requested
        template: null,
        contacts: null,
        preferred_provider: 'webjs', // Default to WhatsApp Web JS as requested
        whatsapp_session_id: null,
        time: null,
        scheduled_at: null,
        skip_schedule: false,

        // Template campaign fields
        'header' : {
            'format' : null,
            'text' : null,
            'parameters' : []
        },
        'body' : {
            'text' : null,
            'parameters' : []
        },
        'footer' : {
            'text' : null,
        },
        'buttons' : [],

        // Direct message fields
        header_type: 'text',
        header_text: null,
        header_media: null,
        body_text: null,
        footer_text: null,
        buttons: [],
    });

    const loadTemplate = async() => {
        try {
            const response = await axios.get('/templates/' + form.template);
            if(response){
                const metadata = JSON.parse(response.data.metadata);
                form.header.format = extractComponent(metadata, 'HEADER', 'format');

                form.header.text = extractComponent(metadata, 'HEADER', 'text');
                const headerExamples = extractComponent(metadata, 'HEADER', 'example');
                if (headerExamples) {
                    if(form.header.format === 'TEXT'){
                        form.header.parameters = headerExamples.header_text.map(item => ({
                            type: 'text',
                            selection: 'static',
                            value: item,
                        }));
                    } else if(form.header.format === 'IMAGE' || form.header.format === 'DOCUMENT' || form.header.format === 'VIDEO'){
                        form.header.parameters = headerExamples.header_handle.map(item => ({
                            type: form.header.format,
                            selection: 'default',
                            value: null,
                            url: item,
                        }));
                    }
                } else {
                    form.header.parameters = [];
                }

                //console.log(metadata);
                
                form.body.text = extractComponent(metadata, 'BODY', 'text');
                const bodyExamples = extractComponent(metadata, 'BODY', 'example');
                if (bodyExamples) {
                    form.body.parameters = bodyExamples.body_text[0].map(item => ({
                        type: 'text',
                        selection: 'static',
                        value: item,
                    }));
                } else {
                    form.body.parameters = [];
                }

                form.footer.text = extractComponent(metadata, 'FOOTER', 'text');

                const buttons = extractComponent(metadata, 'BUTTONS', 'buttons');
                if (buttons) {
                    form.buttons = buttons.map(item => ({
                        type: item.type,
                        text: item.text,
                        value: item[item.type.toLowerCase()] ?? null,
                        parameters: (item.type === 'QUICK_REPLY')
                            ? [{ type: 'static', value: null }]
                            : (item.example
                                ? item.example.map(param => ({ type: 'static', value: param }))
                                : []
                            ),
                    }));
                } else {
                    form.buttons = [];
                }

                //console.log(form.buttons)
            }
        } catch (error) {
            //console.error('Error fetching data:', error);
        }
    }

    const handleFileUpload = (event) => {
        const fileSizeLimit = getFileSizeLimit(form.header.parameters[0].type);
        const file = event.target.files[0];

        if (file && file.size > fileSizeLimit) {
            // Handle file size exceeding the limit
            alert(trans('file size exceeds the limit. Max allowed size:') + ' ' + fileSizeLimit + 'b');
            // Clear the file input
            event.target.value = null;
        } else {
            const reader = new FileReader();

            reader.onload = (e) => {
                form.header.parameters[0].url = e.target.result;
            };

            form.header.parameters[0].selection = 'upload';
            form.header.parameters[0].value = file;

            // Start reading the file
            reader.readAsDataURL(file);
        }
    }

    const getFileAcceptAttribute = (fileType) => {
        switch (fileType) {
            case 'IMAGE':
                return '.png, .jpg';
            case 'DOCUMENT':
                return '.pdf, .txt, .ppt, .doc, .xls, .docx, .pptx, .xlsx';
            case 'VIDEO':
                return '.mp4';
            default:
                return '';
        }
    }

    const getFileSizeLimit = (fileType) => {
        switch (fileType) {
            case 'IMAGE':
                return 5 * 1024 * 1024; // 5MB
            case 'DOCUMENT':
                return 100 * 1024 * 1024; // 100MB
            case 'VIDEO':
                return 16 * 1024 * 1024; // 16MB
            default:
                return Infinity;
        }
    }

    const extractComponent = (data, type, customProperty) => {
        const component = data.components.find(
            (c) => c.type === type
        );

        return component ? component[customProperty] : null;
    };

    const transformOptions = (options) => {
        return options.map((option) => ({
            value: option.uuid,
            label: option.language ? option.name + ' [' + option.language + ']' : option.name,
        }));
    };

    // Computed properties for hybrid functionality
    const isTemplateMode = computed(() => form.campaign_type === 'template');
    const isDirectMode = computed(() => form.campaign_type === 'direct');
    const hasWebJsSessions = computed(() => props.whatsappSessions?.some(s => s.provider_type === 'webjs') || false);
    const hasMetaApiSessions = computed(() => props.whatsappSessions?.some(s => s.provider_type === 'meta_api') || false);

    // Watch for campaign type changes to reset form fields
    const onCampaignTypeChange = () => {
        // Reset form fields when switching between campaign types
        form.template = null;
        form.header_text = null;
        form.header_media = null;
        form.body_text = null;
        form.footer_text = null;
        form.buttons = [];

        // Reset template-specific fields
        form.header = {
            format: null,
            text: null,
            parameters: []
        };
        form.body = {
            text: null,
            parameters: []
        };
        form.footer = {
            text: null,
        };
        form.buttons = [];
    };

    // Add button for direct message campaigns
    const addDirectButton = () => {
        const newButton = {
            type: 'reply',
            text: '',
        };
        form.buttons.push(newButton);
    };

    // Remove button from direct message campaigns
    const removeDirectButton = (index) => {
        form.buttons.splice(index, 1);
    };

    // Handle direct media upload
    const handleDirectMediaUpload = (event) => {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                form.header_media = file;
            };
            reader.readAsDataURL(file);
        }
    };

    const submitForm = () => {
        isLoading.value = true;

        if (isTemplateMode.value) {
            // Use existing template submission logic
            form.post(props.isCampaignFlow ? '/campaigns' : '/chat/' + props.contact + '/send/template', {
                onFinish: () => {
                    isLoading.value = false;
                    if(!props.isCampaignFlow){
                        emit('viewTemplate', false);
                    }
                },
            });
        } else {
            // Use hybrid campaign endpoint for direct messages
            const formData = new FormData();

            // Add all form fields to FormData
            Object.keys(form.data()).forEach(key => {
                if (key === 'buttons' && Array.isArray(form.data()[key])) {
                    formData.append(key, JSON.stringify(form.data()[key]));
                } else if (form.data()[key] !== null && form.data()[key] !== undefined) {
                    formData.append(key, form.data()[key]);
                }
            });

            // Convert FormData to JSON for the hybrid endpoint
            const jsonData = {};
            formData.forEach((value, key) => {
                if (key === 'buttons') {
                    try {
                        jsonData[key] = JSON.parse(value);
                    } catch (e) {
                        jsonData[key] = value;
                    }
                } else {
                    jsonData[key] = value;
                }
            });

            form.transform(() => jsonData).post('/campaigns/hybrid', {
                onFinish: () => {
                    isLoading.value = false;
                },
                onSuccess: () => {
                    // Reset form on success
                    form.reset();
                    form.campaign_type = 'direct'; // Reset to default
                    form.preferred_provider = 'webjs'; // Reset to default
                }
            });
        }
    }

    const emit = defineEmits(['viewTemplate']);

    const viewTemplate = () => {
        emit('viewTemplate', false);
    }

    onMounted(() => {
        templateOptions.value = transformOptions(props.templates);
        contactGroupOptions.value = [...contactGroupOptions.value, ...transformOptions(props.contactGroups)];
    });
</script>
<template>
    <div :class="'md:flex md:flex-grow-1'">
        <div v-if="!isWhatsAppConnected" class="md:w-[50%] p-4 md:p-8 overflow-y-auto h-[90vh]">
            <div class="bg-slate-50 border border-primary shadow rounded-md p-4 py-8">
                <div class="flex justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 48 48"><path fill="black" d="M43.634 4.366a1.25 1.25 0 0 1 0 1.768l-4.913 4.913a9.253 9.253 0 0 1-.744 12.244l-3.343 3.343a1.25 1.25 0 0 1-1.768 0l-11.5-11.5a1.25 1.25 0 0 1 0-1.768l3.343-3.343a9.25 9.25 0 0 1 12.244-.743l4.913-4.914a1.25 1.25 0 0 1 1.768 0m-7.611 7.425a6.75 6.75 0 0 0-9.546 0l-2.46 2.459l9.733 9.732l2.46-2.459a6.75 6.75 0 0 0 0-9.546zM9.28 36.953l-4.914 4.913a1.25 1.25 0 0 0 1.768 1.768l4.913-4.913a9.253 9.253 0 0 0 12.244-.744l3.343-3.343a1.25 1.25 0 0 0 0-1.768L25.268 31.5l3.366-3.366a1.25 1.25 0 0 0-1.768-1.768L23.5 29.732L18.268 24.5l3.366-3.366a1.25 1.25 0 0 0-1.768-1.768L16.5 22.732l-1.366-1.366a1.25 1.25 0 0 0-1.768 0l-3.343 3.343a9.25 9.25 0 0 0-.743 12.244m2.51-10.476l2.46-2.46l9.732 9.733l-2.459 2.46a6.75 6.75 0 0 1-9.546 0l-.186-.187a6.75 6.75 0 0 1 0-9.546"/></svg>
                </div>
                <h3 class="text-center text-lg font-medium mb-4">{{ $t('Connect your whatsapp account') }}</h3>
                <h4 class="text-center mb-4">{{ $t('You need to connect your WhatsApp account first before you can send out campaigns.') }}</h4>

                <!-- Show connection status details -->
                <div class="text-center mb-4 text-sm text-gray-600">
                    <div v-if="!settings?.whatsapp && (!whatsappSessions || whatsappSessions.length === 0)" class="mb-2">
                        <p class="mb-2">{{ $t('No WhatsApp connection found. You can connect via:') }}</p>
                        <div class="space-y-1">
                            <p>• {{ $t('Meta API (Business API)') }}</p>
                            <p>• {{ $t('WhatsApp Web JS (Direct connection)') }}</p>
                        </div>
                    </div>
                    <div v-if="!settings?.whatsapp && whatsappSessions && whatsappSessions.length > 0" class="mb-2">
                        <p class="text-green-600">{{ $t('WhatsApp Web JS sessions found, but none are connected.') }}</p>
                    </div>
                    <div v-if="settings?.whatsapp && (!whatsappSessions || whatsappSessions.length === 0)" class="mb-2">
                        <p class="text-green-600">{{ $t('Meta API is configured.') }}</p>
                    </div>
                </div>

                <div class="flex justify-center space-x-3">
                    <Link href="/settings/whatsapp" class="rounded-md px-3 py-2 text-sm hover:shadow-md text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-primary" :disabled="isLoading">
                        <span v-if="!isLoading">{{ $t('Connect Meta API') }}</span>
                    </Link>
                    <Link href="/settings/whatsapp/sessions" class="rounded-md px-3 py-2 text-sm hover:shadow-md text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 bg-green-600" :disabled="isLoading">
                        <span v-if="!isLoading">{{ $t('Manage WhatsApp Sessions') }}</span>
                    </Link>
                </div>
            </div>
        </div>

        <form v-else @submit.prevent="submitForm()" class="overflow-y-auto md:w-[50%]" :class="isCampaignFlow ? 'p-4 md:p-8 h-[90vh]' : ' h-full'">
            <div v-if="displayTitle" class="m-1 rounded px-3 pt-3 pb-3 bg-slate-100 flex items-center justify-between mb-4">
                <h3 class="text-[15px]">{{ isTemplateMode ? $t('Send Template Message') : $t('Send Direct Message') }}</h3>
                <button @click="viewTemplate()" class="text-sm md:inline-flex hidden justify-center rounded-md border border-transparent bg-red-800 px-4 py-1 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Cancel</button>
            </div>

            <!-- Campaign Configuration Section -->
            <div v-if="isCampaignFlow" class="grid gap-x-6 gap-y-4 mb-6 sm:grid-cols-6 p-3 md:p-3">
                <!-- Campaign Name -->
                <FormInput v-model="form.name" :name="$t('Campaign name')" :type="'text'" :error="form.errors.name" :required="true" :class="'sm:col-span-6'"/>

                <!-- Campaign Type Selection -->
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">
                        {{ $t('Campaign Type') }}
                    </label>
                    <div class="space-y-3">
                        <div v-for="type in campaignTypeOptions" :key="type.value" class="relative flex items-center">
                            <input
                                :id="'campaign-type-' + type.value"
                                v-model="form.campaign_type"
                                @change="onCampaignTypeChange"
                                :value="type.value"
                                name="campaign_type"
                                type="radio"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                            >
                            <label :for="'campaign-type-' + type.value" class="ml-3 flex flex-col">
                                <span class="block text-sm font-medium text-gray-900">{{ type.label }}</span>
                                <span class="block text-sm text-gray-500">{{ type.description }}</span>
                            </label>
                        </div>
                    </div>
                    <div v-if="form.errors.campaign_type" class="mt-1 text-sm text-red-600">{{ form.errors.campaign_type }}</div>
                </div>

                <!-- Provider Selection -->
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">
                        {{ $t('Preferred WhatsApp Provider') }}
                    </label>
                    <div class="space-y-3">
                        <div v-for="provider in providerSelectOptions" :key="provider.value" class="relative flex items-center">
                            <input
                                :id="'provider-' + provider.value"
                                v-model="form.preferred_provider"
                                :value="provider.value"
                                name="preferred_provider"
                                type="radio"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                :disabled="(provider.value === 'webjs' && !hasWebJsSessions) || (provider.value === 'meta_api' && !hasMetaApiSessions)"
                            >
                            <label :for="'provider-' + provider.value" class="ml-3 flex flex-col">
                                <span class="block text-sm font-medium text-gray-900">{{ provider.label }}</span>
                                <span class="block text-sm text-gray-500">{{ provider.description }}</span>
                                <span v-if="(provider.value === 'webjs' && !hasWebJsSessions) || (provider.value === 'meta_api' && !hasMetaApiSessions)"
                                      class="block text-sm text-amber-600">
                                    {{ $t('No active sessions available') }}
                                </span>
                            </label>
                        </div>
                    </div>
                    <div v-if="form.errors.preferred_provider" class="mt-1 text-sm text-red-600">{{ form.errors.preferred_provider }}</div>
                </div>

                <!-- WhatsApp Session Selection -->
                <FormSelect
                    v-if="props.whatsappSessions && props.whatsappSessions.length > 0"
                    v-model="form.whatsapp_session_id"
                    :options="props.whatsappSessions.map(s => ({
                        value: s.id,
                        label: `${s.formatted_phone_number} (${s.provider_type === 'webjs' ? 'WebJS' : 'Meta API'}) - Health: ${s.health_score}%`
                    }))"
                    :name="$t('Specific WhatsApp Session (Optional)')"
                    :class="'sm:col-span-6'"
                    :placeholder="$t('Auto-select best session')"
                    :error="form.errors.whatsapp_session_id"
                />

                <!-- Contact Group Selection -->
                <FormSelect v-model="form.contacts" :options="contactGroupOptions" :name="$t('Send to')" :required="true" :class="'sm:col-span-3'" :placeholder="$t('Select contacts')" :error="form.errors.contacts"/>

                <!-- Scheduling Options -->
                <FormInput v-if="!form.skip_schedule" v-model="form.time" :name="$t('Scheduled time')" :type="'datetime-local'" :error="form.errors.time" :required="true" :class="'sm:col-span-2'"/>
                <div class="relative flex gap-x-3 sm:col-span-6 items-center">
                    <div class="flex h-6 items-center">
                        <input v-model="form.skip_schedule" id="skip-schedule" name="skip-schedule" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                    </div>
                    <div class="text-sm leading-6">
                        <label for="skip-schedule" class="font-medium text-gray-900">{{ $t('Skip scheduling & send immediately') }}</label>
                    </div>
                </div>
            </div>

            <!-- Template-specific Configuration -->
            <div v-if="isTemplateMode && isCampaignFlow" :class="isCampaignFlow ? '' : 'px-3 md:px-3'">
                <div class="mb-4">
                    <FormSelect v-model="form.template" @update:modelValue="loadTemplate" :options="templateOptions" :required="true" :error="form.errors.template" :name="$t('Template')" :placeholder="$t('Select template')"/>
                </div>
            </div>

            <!-- Direct Message Configuration -->
            <div v-if="isDirectMode && isCampaignFlow" class="grid gap-x-6 gap-y-4 mb-6 sm:grid-cols-6 px-3 md:px-3">
                <!-- Header Section -->
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">{{ $t('Message Header') }}</label>
                    <div class="space-y-3">
                        <!-- Header Type Selection -->
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="radio" v-model="form.header_type" value="text" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                <span class="ml-2 text-sm text-gray-900">{{ $t('Text Header') }}</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" v-model="form.header_type" value="image" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                <span class="ml-2 text-sm text-gray-900">{{ $t('Image Header') }}</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" v-model="form.header_type" value="document" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                <span class="ml-2 text-sm text-gray-900">{{ $t('Document Header') }}</span>
                            </label>
                        </div>

                        <!-- Header Text Input -->
                        <FormInput v-if="form.header_type === 'text'" v-model="form.header_text" :name="$t('Header Text')" :type="'text'" :error="form.errors.header_text" :class="'sm:col-span-6'"/>

                        <!-- Header Media Upload -->
                        <div v-if="form.header_type !== 'text'">
                            <label class="block text-sm font-medium text-gray-900">{{ $t('Upload Media') }}</label>
                            <input type="file" @change="handleDirectMediaUpload" :accept="form.header_type === 'image' ? 'image/*' : 'application/pdf'" class="mt-1 block w-full text-sm text-gray-900 border-gray-300 rounded-md"/>
                            <p v-if="form.header_type === 'image'" class="text-xs text-gray-500 mt-1">{{ $t('Supported formats: PNG, JPG, JPEG (Max 5MB)') }}</p>
                            <p v-if="form.header_type === 'document'" class="text-xs text-gray-500 mt-1">{{ $t('Supported formats: PDF (Max 100MB)') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Message Body -->
                <div class="sm:col-span-6">
                    <label for="body_text" class="block text-sm font-medium leading-6 text-gray-900">{{ $t('Message Body') }} *</label>
                    <textarea
                        id="body_text"
                        v-model="form.body_text"
                        rows="6"
                        class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                        :placeholder="$t('Enter your message content here...')"
                        required
                    ></textarea>
                    <div v-if="form.errors.body_text" class="mt-1 text-sm text-red-600">{{ form.errors.body_text }}</div>
                    <div class="mt-2 text-xs text-gray-500">
                        {{ $t('You can use variables like') }}: {{ '{first_name}' }}, {{ '{company}' }}, {{ '{email}' }}
                    </div>
                </div>

                <!-- Footer (Optional) -->
                <div class="sm:col-span-6">
                    <FormInput v-model="form.footer_text" :name="$t('Footer (Optional)')" :type="'text'" :error="form.errors.footer_text" :class="'sm:col-span-6'" :placeholder="$t('Optional footer text')"/>
                </div>

                <!-- Buttons Section -->
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium leading-6 text-gray-900 mb-2">{{ $t('Message Buttons (Optional)') }}</label>
                    <div v-if="form.buttons.length === 0" class="text-sm text-gray-500 mb-2">{{ $t('No buttons added') }}</div>
                    <div v-for="(button, index) in form.buttons" :key="index" class="mb-3 p-3 border border-gray-200 rounded-md">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">{{ $t('Button') }} {{ index + 1 }}</span>
                            <button type="button" @click="removeDirectButton(index)" class="text-red-600 hover:text-red-800 text-sm">{{ $t('Remove') }}</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <FormInput v-model="button.text" :name="$t('Button Text')" :type="'text'" :required="true"/>
                        </div>
                    </div>
                    <button type="button" @click="addDirectButton" class="mt-2 rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Add Button') }}
                    </button>
                </div>
            </div>
            <div :class="isCampaignFlow ? '' : 'px-3 md:px-3'">
                <div v-if="form.header.parameters.length > 0" class="bg-slate-100 p-3 mt-4 text-sm">
                    <h2 class="flex items-center justify-between space-x-2 pb-2 border-b">
                        <div class="flex items-center space-x-2">
                            <span>{{ $t('Header variables') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M512 64a448 448 0 1 1 0 896.064A448 448 0 0 1 512 64zm67.2 275.072c33.28 0 60.288-23.104 60.288-57.344s-27.072-57.344-60.288-57.344c-33.28 0-60.16 23.104-60.16 57.344s26.88 57.344 60.16 57.344zM590.912 699.2c0-6.848 2.368-24.64 1.024-34.752l-52.608 60.544c-10.88 11.456-24.512 19.392-30.912 17.28a12.992 12.992 0 0 1-8.256-14.72l87.68-276.992c7.168-35.136-12.544-67.2-54.336-71.296c-44.096 0-108.992 44.736-148.48 101.504c0 6.784-1.28 23.68.064 33.792l52.544-60.608c10.88-11.328 23.552-19.328 29.952-17.152a12.8 12.8 0 0 1 7.808 16.128L388.48 728.576c-10.048 32.256 8.96 63.872 55.04 71.04c67.84 0 107.904-43.648 147.456-100.416z"/></svg>
                        </div>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M6.102 16.98c-1.074 0-1.648-1.264-.94-2.073l5.521-6.31a1.75 1.75 0 0 1 2.634 0l5.522 6.31c.707.809.133 2.073-.94 2.073H6.101Z"/></svg>
                        </span>
                    </h2>
                    <div v-for="(item, index) in form.header.parameters" :key="index" class="mt-2 flex items-center space-x-4">
                        <div v-if="form.header.parameters[index].type === 'text'" class="w-full">
                            <FormSelect v-model="form.header.parameters[index].selection" :name="$t('Content type')" :options="variableOptions" :class="'sm:col-span-6'"/>
                        </div>
                        <div v-if="form.header.parameters[index].type === 'text'" class="w-full">
                            <FormInput v-if="form.header.parameters[index].selection === 'static'" :name="$t('Value')" :required="true" :error="form.errors['header.parameters.0.value']" v-model="form.header.parameters[index].value" :type="'text'" :class="'sm:col-span-6'"/>
                            <FormSelect v-if="form.header.parameters[index].selection === 'dynamic'" :name="$t('Value')" :required="true" :error="form.errors['header.parameters.0.value']" v-model="form.header.parameters[index].value" :options="dynamicOptions" :class="'sm:col-span-6'"/>
                        </div>
                        <div v-if="['IMAGE', 'DOCUMENT', 'VIDEO'].includes(form.header.parameters[index].type)" class="w-full mt-3">
                            <div>
                                <div class="flex items-center space-x-4">
                                    <label class="cursor-pointer flex justify-center px-2 py-2 w-[30%] bg-slate-200 shadow-sm rounded-lg border" 
                                        :class="form.errors['header.parameters.0.value'] ? 'border border-red-700' : ''" for="file-upload">
                                        {{ $t('Upload') }}
                                    </label>
                                    <input type="file" class="sr-only" :accept="getFileAcceptAttribute(form.header.parameters[index].type)" ref="fileInput" id="file-upload" @change="handleFileUpload"/>
                                    <div v-if="form.header.parameters[index].value" class="w-[20em] truncate">{{ form.header.parameters[index].selection === 'default' ? form.header.parameters[index].value : form.header.parameters[index].value.name }}</div>
                                    <span v-else>{{ $t('No file chosen') }}</span>
                                </div>
                                <p v-if="form.header.parameters[index].type === 'IMAGE'" class="text-left text-xs mt-2">{{ $t('Max file upload size is') }} <b>5MB</b> <br> {{ $t('Supported file extensions') }}: .png, jpg</p>
                                <p v-if="form.header.parameters[index].type === 'DOCUMENT'" class="text-left text-xs mt-2">{{ $t('Max file upload size is') }} <b>100MB</b> <br> {{ $t('Supported file extensions') }}: .pdf, .txt, .ppt, .doc, .xls, .docx, .pptx, .xlsx</p>
                                <p v-if="form.header.parameters[index].type === 'VIDEO'" class="text-left text-xs mt-2">{{ $t('Max file upload size is') }} <b>16MB</b> <br> {{ $t('Supported file extensions') }}: .mp4</p>
                            </div>
                            
                            <div v-if="form.errors['header.parameters.0.value']" class="form-error text-[#b91c1c] text-xs">{{ form.errors['header.parameters.0.value'] }}</div>
                        </div>
                    </div>
                </div>
                <div v-if="form.body.parameters.length > 0" class="bg-slate-100 p-3 mt-1 text-sm">
                    <h2 class="flex items-center justify-between space-x-2 pb-2 border-b">
                        <div class="flex items-center space-x-2">
                            <span>{{ $t('Body variables') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M512 64a448 448 0 1 1 0 896.064A448 448 0 0 1 512 64zm67.2 275.072c33.28 0 60.288-23.104 60.288-57.344s-27.072-57.344-60.288-57.344c-33.28 0-60.16 23.104-60.16 57.344s26.88 57.344 60.16 57.344zM590.912 699.2c0-6.848 2.368-24.64 1.024-34.752l-52.608 60.544c-10.88 11.456-24.512 19.392-30.912 17.28a12.992 12.992 0 0 1-8.256-14.72l87.68-276.992c7.168-35.136-12.544-67.2-54.336-71.296c-44.096 0-108.992 44.736-148.48 101.504c0 6.784-1.28 23.68.064 33.792l52.544-60.608c10.88-11.328 23.552-19.328 29.952-17.152a12.8 12.8 0 0 1 7.808 16.128L388.48 728.576c-10.048 32.256 8.96 63.872 55.04 71.04c67.84 0 107.904-43.648 147.456-100.416z"/></svg>
                        </div>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M6.102 16.98c-1.074 0-1.648-1.264-.94-2.073l5.521-6.31a1.75 1.75 0 0 1 2.634 0l5.522 6.31c.707.809.133 2.073-.94 2.073H6.101Z"/></svg>
                        </span>
                    </h2>
                    <div v-for="(item, index) in form.body.parameters" :key="index" class="mt-2 flex items-center space-x-4">
                        <div class="w-[30%]">
                            <span v-text="'{{' + (index + 1) + '}}'"></span>
                        </div>
                        <div class="w-full">
                            <FormSelect v-model="form.body.parameters[index].selection" :options="variableOptions" :class="'sm:col-span-6'"/>
                        </div>
                        <div class="w-full">
                            <FormInput v-if="form.body.parameters[index].selection === 'static'" v-model="form.body.parameters[index].value" :required="true" :error="form.errors['body.parameters.0.value']" :type="'text'" :class="'sm:col-span-6'"/>
                            <FormSelect v-if="form.body.parameters[index].selection === 'dynamic'" v-model="form.body.parameters[index].value" :required="true" :error="form.errors['body.parameters.0.value']" :options="dynamicOptions" :class="'sm:col-span-6'"/>
                        </div>
                    </div>
                </div>
                <div v-if="form.buttons.length > 0" class="bg-slate-100 p-3 mt-1 text-sm">
                    <h2 class="flex items-center justify-between space-x-2 pb-2 border-b">
                        <div class="flex items-center space-x-2">
                            <span>{{ $t('Button variables') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024"><path fill="currentColor" d="M512 64a448 448 0 1 1 0 896.064A448 448 0 0 1 512 64zm67.2 275.072c33.28 0 60.288-23.104 60.288-57.344s-27.072-57.344-60.288-57.344c-33.28 0-60.16 23.104-60.16 57.344s26.88 57.344 60.16 57.344zM590.912 699.2c0-6.848 2.368-24.64 1.024-34.752l-52.608 60.544c-10.88 11.456-24.512 19.392-30.912 17.28a12.992 12.992 0 0 1-8.256-14.72l87.68-276.992c7.168-35.136-12.544-67.2-54.336-71.296c-44.096 0-108.992 44.736-148.48 101.504c0 6.784-1.28 23.68.064 33.792l52.544-60.608c10.88-11.328 23.552-19.328 29.952-17.152a12.8 12.8 0 0 1 7.808 16.128L388.48 728.576c-10.048 32.256 8.96 63.872 55.04 71.04c67.84 0 107.904-43.648 147.456-100.416z"/></svg>
                        </div>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M6.102 16.98c-1.074 0-1.648-1.264-.94-2.073l5.521-6.31a1.75 1.75 0 0 1 2.634 0l5.522 6.31c.707.809.133 2.073-.94 2.073H6.101Z"/></svg>
                        </span>
                    </h2>
                    <div v-for="(item, index) in form.buttons" :key="index">
                        <div v-if="item.parameters.length > 0" class="mt-4 bg-slate-50 p-3">
                            <div class="w-[100%] mb-1">
                                <span>{{ $t('Label') }}: {{ item.text }}</span>
                            </div>
                            <div v-for="(value, key) in item.parameters" :key="key" class="flex items-center space-x-4">
                                <div class="w-full">
                                    <FormSelect v-model="value.type" :name="$t('Button type')" :options="variableOptions" :class="'sm:col-span-6'"/>
                                </div>
                                <div class="w-full">
                                    <FormInput v-if="value.type === 'static'" v-model="value.value" :name="$t('Value')" :required="true" :error="form.errors['buttons.'+ index +'.parameters.0.value']" :type="'text'" :class="'sm:col-span-6'"/>
                                    <FormSelect v-if="value.type === 'dynamic'" v-model="value.value" :name="$t('Value')" :required="true" :error="form.errors['buttons.'+ index +'.parameters.0.value']" :options="dynamicOptions" :class="'sm:col-span-6'"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-3 mt-3">
                    <div v-if="displayCancelBtn">
                        <Link href="/campaigns" class="block rounded-md px-3 py-2 text-sm text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-slate-200">
                            {{ $t('Cancel') }}
                        </Link>
                    </div>
                    <div>
                        <button type="submit" class="rounded-md px-3 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-primary" :disabled="isLoading">
                            <span v-if="!isLoading">{{ sendText ? sendText : $t('Save') }}</span>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <div class="md:w-[50%] py-20 flex justify-center chat-bg" :class="isCampaignFlow ? 'px-20' : 'px-10'">
            <div>
                <!-- Show template preview for template campaigns -->
                <WhatsappTemplate v-if="isTemplateMode" :parameters="form" :visible="form.template ? true : false"/>

                <!-- Show direct message preview for direct campaigns -->
                <div v-else-if="isDirectMode && form.body_text" class="bg-white rounded-lg shadow-lg max-w-sm mx-auto">
                    <div class="bg-green-500 p-4 rounded-t-lg flex items-center space-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12.043 23c6.623 0 12-5.377 12-12s-5.377-12-12-12s-12 5.377-12 12s5.377 12 12 12M12.043 7a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2m0 12a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3Z"/></svg>
                        <span class="text-white font-medium">{{ $t('Message Preview') }}</span>
                    </div>
                    <div class="p-4">
                        <!-- Header Preview -->
                        <div v-if="form.header_text" class="font-semibold text-gray-900 mb-2">{{ form.header_text }}</div>
                        <div v-if="form.header_type === 'image' && form.header_media" class="mb-2 text-sm text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1m1 2v8h14V7zm3 2h8v2H8zm0 4h5v2H8z"/></svg>
                            {{ $t('Image attached') }}
                        </div>
                        <div v-if="form.header_type === 'document' && form.header_media" class="mb-2 text-sm text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6m4 18H6V4h7v5h5v11Z"/></svg>
                            {{ $t('Document attached') }}
                        </div>

                        <!-- Body Preview -->
                        <div class="text-gray-800 mb-3 whitespace-pre-wrap">{{ form.body_text }}</div>

                        <!-- Footer Preview -->
                        <div v-if="form.footer_text" class="text-xs text-gray-500 mb-3">{{ form.footer_text }}</div>

                        <!-- Buttons Preview -->
                        <div v-if="form.buttons && form.buttons.length > 0" class="space-y-2">
                            <div v-for="(button, index) in form.buttons" :key="index" class="bg-gray-100 p-2 rounded text-center text-sm text-blue-600">
                                {{ button.text }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Show empty state for direct campaigns without content -->
                <div v-else-if="isDirectMode" class="bg-white rounded-lg shadow-lg max-w-sm mx-auto p-8 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" class="mx-auto text-gray-400 mb-4"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    <p class="text-gray-500 text-sm">{{ $t('Start typing your message to see a preview') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>