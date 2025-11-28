<script setup>
    import axios from "axios";
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import BodyTextArea from '@/Components/Template/BodyTextArea.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
    import SpeedTierSelector from '@/Components/Campaign/SpeedTierSelector.vue';
    import { ref, computed, onMounted, watch } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import 'vue3-toastify/dist/index.css';
    import { trans } from 'laravel-vue-i18n';

    const props = defineProps({
        templates: Object,
        contactGroups: Object,
        settings: Array,
        whatsappAccounts: {
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
        speedTiers: {
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
            default: 'Send'
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

        // Check for WhatsApp Web JS accounts
        const hasWebJsAccounts = props.whatsappAccounts && props.whatsappAccounts.length > 0;

        return hasMetaApi || hasWebJsAccounts;
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
        whatsapp_account_id: null,
        speed_tier: 2, // Default to 'safe' tier
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
        // Handle both draft format (components at root) and Meta API format (components in metadata)
        const components = data.components || data;
        
        if (!Array.isArray(components)) return null;
        
        const component = components.find(
            (c) => c.type === type
        );

        return component ? component[customProperty] : null;
    };

    const transformOptions = (options) => {
        return options.map((option) => ({
            value: option.uuid,
            label: option.language 
                ? `${option.name} [${option.language}]${option.status === 'DRAFT' ? ' (Draft)' : ''}`
                : `${option.name}${option.status === 'DRAFT' ? ' (Draft)' : ''}`,
            status: option.status,
            requires_meta_api: option.requires_meta_api || false,
            webjs_compatible: option.webjs_compatible !== false,
        }));
    };

    // Filter templates based on selected provider
    const filteredTemplateOptions = computed(() => {
        const allOptions = transformOptions(props.templates || []);
        
        if (form.preferred_provider === 'meta_api') {
            // Meta API can only use APPROVED templates
            return allOptions.filter(opt => opt.status === 'APPROVED');
        }
        
        // WebJS can use all templates (APPROVED and DRAFT)
        return allOptions;
    });

    // Computed properties for hybrid functionality
    const isTemplateMode = computed(() => form.campaign_type === 'template');
    const isDirectMode = computed(() => form.campaign_type === 'direct');
    const hasWebJsAccounts = computed(() => props.whatsappAccounts?.some(a => a.provider_type === 'webjs') || false);
    const hasMetaApiAccounts = computed(() => props.whatsappAccounts?.some(a => a.provider_type === 'meta_api') || false);

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

    // Footer character count
    const footerCharacterCount = computed(() => {
        return form.footer_text ? form.footer_text.length : 0;
    });

    // Handle body examples from BodyTextArea
    const updateBodyExamples = (value) => {
        // For direct campaigns, we don't need to store examples in body parameters
        // but we can use this for future enhancement
    };

    // Character count function for fields with limits
    const characterCount = (type) => {
        let limit = 60; // Footer character limit
        let count = 0;

        switch (type) {
            case 'footer':
                limit = 60;
                count = form.footer_text ? form.footer_text.length : 0;
                if (count <= limit) {
                    // Update computed property will handle this
                } else {
                    form.footer_text = form.footer_text.slice(0, limit);
                }
                break;
        }
    };

    // Add button for direct message campaigns with specific types
    const addDirectButton = (type) => {
        if(type === 'call'){
            const buttonsCount = form.buttons.filter(button => button.type === 'PHONE_NUMBER').length;
            if(buttonsCount < 1){
                form.buttons.push({
                    'type' : 'PHONE_NUMBER',
                    'country' : null,
                    'text' : null,
                    'phone_number' : null,
                });
            }
        } else if(type === 'website'){
            const buttonsCount = form.buttons.filter(button => button.type === 'URL').length;
            if(buttonsCount < 2){
                form.buttons.push({
                    'type' : 'URL',
                    'text' : null,
                    'url' : null,
                });
            }
        } else if(type === 'custom'){
            const buttonsCount = form.buttons.filter(button => button.type === 'QUICK_REPLY').length;
            if(buttonsCount < 6){
                form.buttons.push({
                    'type' : 'QUICK_REPLY',
                    'text' : null,
                });
            }
        } else if(type === 'offer'){
            const buttonsCount = form.buttons.filter(button => button.type === 'copy_code').length;
            if(buttonsCount < 1){
                form.buttons.push({
                    'type' : 'copy_code',
                    'example' : null,
                });
            }
        }
    };

    // Remove button from direct message campaigns
    const removeDirectButton = (index) => {
        if (index >= 0 && index < form.buttons.length) {
            form.buttons.splice(index, 1);
        }
    };

    // Handle direct media upload
    const handleDirectMediaUpload = (event, mediaType) => {
        const file = event.target.files[0];
        if (file) {
            form.header_media = file;
        }
    };

    // Remove direct media
    const removeDirectMedia = () => {
        form.header_media = null;
    };

    // Format button text for display
    const formatButtonText = (type) => {
        const buttonLabels = {
            'QUICK_REPLY': 'Custom button',
            'URL': 'Visit website',
            'PHONE_NUMBER': 'Call phone number',
            'copy_code': 'Copy offer code'
        };
        return buttonLabels[type] || type;
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
            // Normalize button types for backend validation
            const normalizedButtons = form.buttons.map(button => ({
                type: button.type === 'PHONE_NUMBER' ? 'phone_number' : 
                      button.type === 'URL' ? 'url' : 
                      button.type === 'QUICK_REPLY' ? 'reply' : 
                      button.type === 'copy_code' ? 'reply' : button.type.toLowerCase(),
                text: button.text,
                url: button.url || null,
                phone_number: button.phone_number || null,
                country: button.country || null,
                example: button.example || null,
            }));

            // Update form buttons with normalized data
            form.buttons = normalizedButtons;
            
            // Map 'time' field to 'scheduled_at' for backend
            if (form.time && !form.skip_schedule) {
                form.scheduled_at = form.time;
            }

            // Use hybrid campaign endpoint for direct messages
            form.post('/campaigns/hybrid', {
                onFinish: () => {
                    isLoading.value = false;
                },
                onSuccess: () => {
                    // Reset form on success
                    form.reset();
                    form.campaign_type = 'direct'; // Reset to default
                    form.preferred_provider = 'webjs'; // Reset to default
                },
                onError: (errors) => {
                    console.error('Campaign creation failed:', errors);
                }
            });
        }
    }

    const emit = defineEmits(['viewTemplate']);

    const viewTemplate = () => {
        emit('viewTemplate', false);
    }

    // Add reactive preview data
    const previewData = ref({
        header_text: '',
        header_type: 'text',
        header_media: null,
        body_text: '',
        footer_text: '',
        buttons: []
    });

    // Watch form fields for real-time preview updates
    watch(() => form.header_text, (newValue) => {
        previewData.value.header_text = newValue;
    });

    watch(() => form.header_type, (newValue) => {
        previewData.value.header_type = newValue;
    });

    watch(() => form.header_media, (newValue) => {
        previewData.value.header_media = newValue;
    });

    watch(() => form.body_text, (newValue) => {
        previewData.value.body_text = newValue;
    });

    watch(() => form.footer_text, (newValue) => {
        previewData.value.footer_text = newValue;
    });

    watch(() => form.buttons, (newValue) => {
        previewData.value.buttons = newValue || [];
    }, { deep: true });

    onMounted(() => {
        // Note: templateOptions is now handled by computed 'filteredTemplateOptions'
        contactGroupOptions.value = [...contactGroupOptions.value, ...transformOptions(props.contactGroups)];

        // Initialize preview data with current form values
        previewData.value = {
            header_text: form.header_text || '',
            header_type: form.header_type || 'text',
            header_media: form.header_media || null,
            body_text: form.body_text || '',
            footer_text: form.footer_text || '',
            buttons: form.buttons || []
        };
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
                    <div v-if="!settings?.whatsapp && (!whatsappAccounts || whatsappAccounts.length === 0)" class="mb-2">
                        <p class="mb-2">{{ $t('No WhatsApp connection found. You can connect via:') }}</p>
                        <div class="space-y-1">
                            <p>• {{ $t('Meta API (Business API)') }}</p>
                            <p>• {{ $t('WhatsApp Web JS (Direct connection)') }}</p>
                        </div>
                    </div>
                    <div v-if="!settings?.whatsapp && whatsappAccounts && whatsappAccounts.length > 0" class="mb-2">
                        <p class="text-green-600">{{ $t('WhatsApp Web JS accounts found, but none are connected.') }}</p>
                    </div>
                    <div v-if="settings?.whatsapp && (!whatsappAccounts || whatsappAccounts.length === 0)" class="mb-2">
                        <p class="text-green-600">{{ $t('Meta API is configured.') }}</p>
                    </div>
                </div>

                <div class="flex justify-center space-x-3">
                    <Link href="/settings/whatsapp" class="rounded-md px-3 py-2 text-sm hover:shadow-md text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-primary" :disabled="isLoading">
                        <span v-if="!isLoading">{{ $t('Connect Meta API') }}</span>
                    </Link>
                    <Link href="/settings/whatsapp/accounts" class="rounded-md px-3 py-2 text-sm hover:shadow-md text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 bg-green-600" :disabled="isLoading">
                        <span v-if="!isLoading">{{ $t('Manage WhatsApp Accounts') }}</span>
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
                    <FormSelect
                        v-model="form.campaign_type"
                        @update:modelValue="onCampaignTypeChange"
                        :options="campaignTypeOptions.map(type => ({
                            value: type.value,
                            label: type.label,
                            disabled: false
                        }))"
                        :name="$t('Campaign Type')"
                        :required="true"
                        :class="'sm:col-span-6'"
                        :placeholder="$t('Select campaign type')"
                        :error="form.errors.campaign_type"
                    />
                    <div class="mt-1 text-xs text-gray-500">
                        {{ campaignTypeOptions.find(type => type.value === form.campaign_type)?.description }}
                    </div>
                </div>

                <!-- Provider Selection -->
                <div class="sm:col-span-6">
                    <FormSelect
                        v-model="form.preferred_provider"
                        :options="providerSelectOptions.map(provider => ({
                            value: provider.value,
                            label: provider.label,
                            disabled: (provider.value === 'webjs' && !hasWebJsAccounts) || (provider.value === 'meta_api' && !hasMetaApiAccounts)
                        }))"
                        :name="$t('Preferred WhatsApp Provider')"
                        :required="true"
                        :class="'sm:col-span-6'"
                        :placeholder="$t('Select preferred provider')"
                        :error="form.errors.preferred_provider"
                    />
                    <div class="mt-1 text-xs text-gray-500">
                        {{ providerSelectOptions.find(provider => provider.value === form.preferred_provider)?.description }}
                        <span v-if="(form.preferred_provider === 'webjs' && !hasWebJsAccounts) || (form.preferred_provider === 'meta_api' && !hasMetaApiAccounts)"
                              class="text-amber-600">
                            {{ $t(' - No active accounts available') }}
                        </span>
                    </div>
                </div>

                <!-- WhatsApp Account Selection -->
                <FormSelect
                    v-if="props.whatsappAccounts && props.whatsappAccounts.length > 0"
                    v-model="form.whatsapp_account_id"
                    :options="props.whatsappAccounts.map(a => ({
                        value: a.id,
                        label: `${a.formatted_phone_number} (${a.provider_type === 'webjs' ? 'WebJS' : 'Meta API'}) - Health: ${a.health_score}%`
                    }))"
                    :name="$t('Specific WhatsApp Account (Optional)')"
                    :class="'sm:col-span-6'"
                    :placeholder="$t('Auto-select best account')"
                    :error="form.errors.whatsapp_account_id"
                />

                <!-- Contact Group Selection -->
                <FormSelect v-model="form.contacts" :options="contactGroupOptions" :name="$t('Send to')" :required="true" :class="'sm:col-span-3'" :placeholder="$t('Select contacts')" :error="form.errors.contacts"/>

                <!-- Speed Tier Selection -->
                <div class="sm:col-span-6">
                    <SpeedTierSelector
                        v-model="form.speed_tier"
                        :tiers="props.speedTiers"
                    />
                </div>

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
                    <FormSelect v-model="form.template" @update:modelValue="loadTemplate" :options="filteredTemplateOptions" :required="true" :error="form.errors.template" :name="$t('Template')" :placeholder="$t('Select template')"/>
                    <p v-if="form.preferred_provider === 'webjs' && filteredTemplateOptions.some(t => t.status === 'DRAFT')" class="text-xs text-blue-600 mt-1">
                        {{ $t('Draft templates are available for WhatsApp Web JS') }}
                    </p>
                    <p v-if="form.preferred_provider === 'meta_api'" class="text-xs text-amber-600 mt-1">
                        {{ $t('Only approved templates can be used with Meta Business API') }}
                    </p>
                </div>
            </div>

            <!-- Direct Message Configuration -->
            <div v-if="isDirectMode && isCampaignFlow" class="px-3 md:px-3">
                <!-- Header Section -->
                <h2 class="text-slate-600">{{ $t('Header') }} <span class="text-xs">({{ $t('Optional') }})</span></h2>
                <span class="text-slate-600 text-xs">{{ $t('Add a title or choose which type of media you\'ll use for this header') }}</span>
                <div class="grid grid-cols-4 mt-2 bg-[#f9f9fa] rounded-lg mb-4">
                    <button @click="form.header_type = 'text'" type="button" class="text-center py-2 text-sm text-slate-800 m-1" :class="form.header_type === 'text' ? 'bg-white shadow rounded-lg' : ''">{{ $t('Text') }}</button>
                    <button @click="form.header_type = 'image'" type="button" class="text-center py-2 text-sm text-slate-800 m-1" :class="form.header_type === 'image' ? 'bg-white shadow rounded-lg' : ''">{{ $t('Image') }}</button>
                    <button @click="form.header_type = 'video'" type="button" class="text-center py-2 text-sm text-slate-800 m-1" :class="form.header_type === 'video' ? 'bg-white shadow rounded-lg' : ''">{{ $t('Video') }}</button>
                    <button @click="form.header_type = 'document'" type="button" class="text-center py-2 text-sm text-slate-800 m-1" :class="form.header_type === 'document' ? 'bg-white shadow rounded-lg' : ''">{{ $t('Document') }}</button>
                </div>
                <div class="mb-8">
                    <!-- Text Header -->
                    <div :class="form.header_type === 'text' ? '' : 'hidden'">
                        <FormInput v-model="form.header_text" :name="$t('Header Text')" :type="'text'" :error="form.errors.header_text" :class="'sm:col-span-6'"/>
                    </div>
                    <!-- Image Header -->
                    <div v-if="form.header_type === 'image'">
                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <input
                                type="file"
                                class="sr-only"
                                accept=".jpg, .png, .jpeg"
                                ref="imageFileInput"
                                @change="handleDirectMediaUpload($event, 'image')"
                            />
                            <div class="text-center">
                                <div>
                                    <div v-if="form.header_media && form.header_type === 'image'" class="flex justify-center items-center">
                                        <div class="flex justify-center items-center space-x-3 py-1 border bg-slate-100 rounded-lg mb-2 w-fit px-2">
                                            <div>
                                                <svg class="mx-auto h-6 w-6 text-gray-400 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14 9a1.5 1.5 0 1 1 3 0a1.5 1.5 0 0 1-3 0Z"/><path fill="currentColor" fill-rule="evenodd" d="M7.268 4.658a54.647 54.647 0 0 1 9.465 0l1.51.132a3.138 3.138 0 0 1 2.831 2.66a30.604 30.604 0 0 1 0 9.1a3.138 3.138 0 0 1-2.831 2.66l-1.51.131c-3.15.274-6.316.274-9.465 0l-1.51-.131a3.138 3.138 0 0 1-2.832-2.66a30.601 30.601 0 0 1 0-9.1a3.138 3.138 0 0 1 2.831-2.66l1.51-.132Zm9.335 1.495a53.147 53.147 0 0 0-9.206 0l-1.51.131A1.638 1.638 0 0 0 4.41 7.672a29.101 29.101 0 0 0-.311 5.17L7.97 8.97a.75.75 0 0 1 1.09.032l3.672 4.13l2.53-.844a.75.75 0 0 1 .796.21l3.519 3.91a29.101 29.101 0 0 0 .014-8.736a1.638 1.638 0 0 0-1.478-1.388l-1.51-.131Zm2.017 11.435l-3.349-3.721l-2.534.844a.75.75 0 0 1-.798-.213l-3.471-3.905l-4.244 4.243c.049.498.11.996.185 1.491a1.638 1.638 0 0 0 1.478 1.389l1.51.131c3.063.266 6.143.266 9.206 0l1.51-.131c.178-.016.35-.06.507-.128Z" clip-rule="evenodd"/></svg>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm">{{ form.header_media instanceof File ? form.header_media.name : 'Media uploaded' }}</span>
                                                <button @click="removeDirectMedia()" type="button">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <label v-else class="cursor-pointer">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14 9a1.5 1.5 0 1 1 3 0a1.5 1.5 0 0 1-3 0Z"/><path fill="currentColor" fill-rule="evenodd" d="M7.268 4.658a54.647 54.647 0 0 1 9.465 0l1.51.132a3.138 3.138 0 0 1 2.831 2.66a30.604 30.604 0 0 1 0 9.1a3.138 3.138 0 0 1-2.831 2.66l-1.51.131c-3.15.274-6.316.274-9.465 0l-1.51-.131a3.138 3.138 0 0 1-2.832-2.66a30.601 30.601 0 0 1 0-9.1a3.138 3.138 0 0 1 2.831-2.66l1.51-.132Zm9.335 1.495a53.147 53.147 0 0 0-9.206 0l-1.51.131A1.638 1.638 0 0 0 4.41 7.672a29.101 29.101 0 0 0-.311 5.17L7.97 8.97a.75.75 0 0 1 1.09.032l3.672 4.13l2.53-.844a.75.75 0 0 1 .796.21l3.519 3.91a29.101 29.101 0 0 0 .014-8.736a1.638 1.638 0 0 0-1.478-1.388l-1.51-.131Zm2.017 11.435l-3.349-3.721l-2.534.844a.75.75 0 0 1-.798-.213l-3.471-3.905l-4.244 4.243c.049.498.11.996.185 1.491a1.638 1.638 0 0 0 1.478 1.389l1.51.131c3.063.266 6.143.266 9.206 0l1.51-.131c.178-.016.35-.06.507-.128Z" clip-rule="evenodd"/></svg>
                                        <div class="flex text-sm text-gray-600">
                                            <span class="relative cursor-pointer bg-white rounded-md font-medium hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                {{ $t('Upload an image for the header') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500">{{ $t('PNG or JPG files only (Max 5MB)') }}</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Video Header -->
                    <div v-if="form.header_type === 'video'">
                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <input
                                type="file"
                                class="sr-only"
                                accept=".mp4, .mov, .avi"
                                ref="videoFileInput"
                                @change="handleDirectMediaUpload($event, 'video')"
                            />
                            <div class="text-center">
                                <div>
                                    <div v-if="form.header_media && form.header_type === 'video'" class="flex justify-center items-center">
                                        <div class="flex justify-center items-center space-x-3 py-1 border bg-slate-100 rounded-lg mb-2 w-fit px-2">
                                            <div>
                                                <svg class="mx-auto h-6 w-6 text-gray-400 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M2 11.5c0-3.287 0-4.931.908-6.038a4 4 0 0 1 .554-.554C4.57 4 6.212 4 9.5 4c3.287 0 4.931 0 6.038.908a4 4 0 0 1 .554.554C17 6.57 17 8.212 17 11.5v1c0 3.287 0 4.931-.908 6.038a4 4 0 0 1-.554.554C14.43 20 12.788 20 9.5 20c-3.287 0-4.931 0-6.038-.908a4 4 0 0 1-.554-.554C2 17.43 2 15.788 2 12.5v-1Zm15-2l.658-.329c1.946-.973 2.92-1.46 3.63-1.02c.712.44.712 1.528.712 3.703v.292c0 2.176 0 3.263-.711 3.703c-.712.44-1.685-.047-3.63-1.02L17 14.5v-5Z"/></svg>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm">{{ form.header_media instanceof File ? form.header_media.name : 'Video uploaded' }}</span>
                                                <button @click="removeDirectMedia()" type="button">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <label v-else class="cursor-pointer">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M2 11.5c0-3.287 0-4.931.908-6.038a4 4 0 0 1 .554-.554C4.57 4 6.212 4 9.5 4c3.287 0 4.931 0 6.038.908a4 4 0 0 1 .554.554C17 6.57 17 8.212 17 11.5v1c0 3.287 0 4.931-.908 6.038a4 4 0 0 1-.554.554C14.43 20 12.788 20 9.5 20c-3.287 0-4.931 0-6.038-.908a4 4 0 0 1-.554-.554C2 17.43 2 15.788 2 12.5v-1Zm15-2l.658-.329c1.946-.973 2.92-1.46 3.63-1.02c.712.44.712 1.528.712 3.703v.292c0 2.176 0 3.263-.711 3.703c-.712.44-1.685-.047-3.63-1.02L17 14.5v-5Z"/></svg>
                                        <div class="flex text-sm text-gray-600">
                                            <span class="relative cursor-pointer bg-white rounded-md font-medium hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                {{ $t('Upload a video for the header') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500">{{ $t('MP4, MOV, or AVI files (Max 16MB)') }}</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Document Header -->
                    <div v-if="form.header_type === 'document'">
                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <input
                                type="file"
                                class="sr-only"
                                accept=".pdf, .doc, .docx, .xls, .xlsx, .ppt, .pptx"
                                ref="documentFileInput"
                                @change="handleDirectMediaUpload($event, 'document')"
                            />
                            <div class="text-center">
                                <div>
                                    <div v-if="form.header_media && form.header_type === 'document'" class="flex justify-center items-center">
                                        <div class="flex justify-center items-center space-x-3 py-1 border bg-slate-100 rounded-lg mb-2 w-fit px-2">
                                            <div>
                                                <svg class="mx-auto h-6 w-6 text-gray-400 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18.53 9L13 3.47a.75.75 0 0 0-.53-.22H8A2.75 2.75 0 0 0 5.25 6v12A2.75 2.75 0 0 0 8 20.75h8A2.75 2.75 0 0 0 18.75 18V9.5a.75.75 0 0 0-.22-.5Zm-5.28-3.19l2.94 2.94h-2.94ZM16 19.25H8A1.25 1.25 0 0 1 6.75 18V6A1.25 1.25 0 0 1 8 4.75h3.75V9.5a.76.76 0 0 0 .75.75h4.75V18A1.25 1.25 0 0 1 16 19.25Z"/><path fill="currentColor" d="M13.49 14.85a3.15 3.15 0 0 1-1.31-1.66a4.44 4.44 0 0 0 .19-2a.8.8 0 0 0-1.52-.19a5 5 0 0 0 .25 2.4A29 29 0 0 1 9.83 16c-.71.4-1.68 1-1.83 1.69c-.12.56.93 2 2.72-1.12a18.58 18.58 0 0 1 2.44-.72a4.72 4.72 0 0 0 2 .61a.82.82 0 0 0 .62-1.38c-.42-.43-1.67-.31-2.29-.23Zm-4.78 3a4.32 4.32 0 0 1 1.09-1.24c-.68 1.08-1.09 1.27-1.09 1.25Zm2.92-6.81c.26 0 .24 1.15.06 1.46a3.07 3.07 0 0 1-.06-1.45Zm-.87 4.88a14.76 14.76 0 0 0 .88-1.92a3.88 3.88 0 0 0 1.08 1.26a12.35 12.35 0 0 0-1.96.67Zm4.7-.18s-.18.22-1.33-.28c1.25-.08 1.46.21 1.33.29Z"/></svg>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm">{{ form.header_media instanceof File ? form.header_media.name : 'Document uploaded' }}</span>
                                                <button @click="removeDirectMedia()" type="button">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <label v-else class="cursor-pointer">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18.53 9L13 3.47a.75.75 0 0 0-.53-.22H8A2.75 2.75 0 0 0 5.25 6v12A2.75 2.75 0 0 0 8 20.75h8A2.75 2.75 0 0 0 18.75 18V9.5a.75.75 0 0 0-.22-.5Zm-5.28-3.19l2.94 2.94h-2.94ZM16 19.25H8A1.25 1.25 0 0 1 6.75 18V6A1.25 1.25 0 0 1 8 4.75h3.75V9.5a.76.76 0 0 0 .75.75h4.75V18A1.25 1.25 0 0 1 16 19.25Z"/><path fill="currentColor" d="M13.49 14.85a3.15 3.15 0 0 1-1.31-1.66a4.44 4.44 0 0 0 .19-2a.8.8 0 0 0-1.52-.19a5 5 0 0 0 .25 2.4A29 29 0 0 1 9.83 16c-.71.4-1.68 1-1.83 1.69c-.12.56.93 2 2.72-1.12a18.58 18.58 0 0 1 2.44-.72a4.72 4.72 0 0 0 2 .61a.82.82 0 0 0 .62-1.38c-.42-.43-1.67-.31-2.29-.23Zm-4.78 3a4.32 4.32 0 0 1 1.09-1.24c-.68 1.08-1.09 1.27-1.09 1.25Zm2.92-6.81c.26 0 .24 1.15.06 1.46a3.07 3.07 0 0 1-.06-1.45Zm-.87 4.88a14.76 14.76 0 0 0 .88-1.92a3.88 3.88 0 0 0 1.08 1.26a12.35 12.35 0 0 0-1.96.67Zm4.7-.18s-.18.22-1.33-.28c1.25-.08 1.46.21 1.33.29Z"/></svg>
                                        <div class="flex text-sm text-gray-600">
                                            <span class="relative cursor-pointer bg-white rounded-md font-medium hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                {{ $t('Upload a document for the header') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500">{{ $t('PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX (Max 100MB)') }}</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Body -->
                <h2 class="text-slate-600">{{ $t('Body') }} <span class="text-xs">({{ $t('Required') }})</span></h2>
                <span class="text-slate-600 text-xs">{{ $t('Enter the text for your message') }}</span>
                <div class="mb-8">
                    <div>
                        <BodyTextArea v-model="form.body_text" @updateExamples="updateBodyExamples"/>
                    </div>
                </div>

                <!-- Footer (Optional) -->
                <h2 class="text-slate-600">{{ $t('Footer description') }} <span class="text-xs">({{ $t('Optional') }})</span></h2>
                <span class="text-slate-600 text-xs">{{ $t('Add a short line of text to the bottom of your message') }}</span>
                <div class="mb-8">
                    <div>
                        <FormTextArea v-model="form.footer_text" @input="characterCount('footer')" :name="$t('Footer text')" :showLabel="false" :type="'text'" :textAreaRows="2" :className="'sm:col-span-6'"/>
                    </div>
                    <span class="text-xs">{{ $t('Characters') }}: {{ footerCharacterCount }}/60</span>
                </div>

                <!-- Buttons Section -->
                <h2 class="text-slate-600">{{ $t('Buttons') }} <span class="text-xs">({{ $t('Optional') }})</span></h2>
                <span class="text-slate-600 text-xs">{{ $t('Create buttons that let customers respond to your message or take action') }}</span>
                <div class="grid grid-cols-2 mt-3 mb-2">
                    <button @click="addDirectButton('call')" type="button" class="flex items-center justify-center text-slate-700 text-sm bg-slate-100 hover:bg-slate-200 hover:shadow-sm rounded-lg p-2 px-4 mr-2">
                        <span>{{ $t('Call phone number (1)') }}</span>
                    </button>
                    <button @click="addDirectButton('website')" type="button" class="flex items-center justify-center text-slate-700 text-sm bg-slate-100 hover:bg-slate-200 hover:shadow-sm rounded-lg p-2 px-4">
                        <span>{{ $t('Visit website (2)') }}</span>
                    </button>
                </div>
                <div class="grid grid-cols-2 mt-3 mb-2">
                    <button @click="addDirectButton('offer')" type="button" class="flex items-center justify-center text-slate-700 text-sm bg-slate-100 hover:bg-slate-200 hover:shadow-sm rounded-lg p-2 px-4 mr-2">
                        <span>{{ $t('Copy offer code (1)') }}</span>
                    </button>
                    <button @click="addDirectButton('custom')" type="button" class="flex items-center justify-center text-slate-700 text-sm bg-slate-100 hover:bg-slate-200 hover:shadow-sm rounded-lg p-2 px-4">
                        <span>{{ $t('Custom button (6)') }}</span>
                    </button>
                </div>
                <div v-if="form.buttons.length > 0" class="mt-3 mb-8">
                    <div v-for="(button, index) in form.buttons" :key="index" class="bg-[#f9f9fa] p-3 rounded-lg mb-3">
                        <div class="flex items-center justify-between pb-1">
                            <span class="text-sm">{{ $t(formatButtonText(button.type)) }}</span>
                            <button @click="removeDirectButton(index)" type="button" class="bg-slate-200 hover:shadow rounded-full p-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="flex space-x-1 border-t pt-2">
                            <FormInput v-model="button.text" :name="$t('Button text')" :type="'text'" :class="button.type === 'QUICK_REPLY' ? 'w-full' :'sm:col-span-2'" :labelClass="'mb-0'"/>
                            <FormInput v-model="button.url" v-if="button.type === 'URL'" :name="$t('Website url')" :type="'url'" :class="'w-full'" :labelClass="'mb-0'"/>
                            <FormInput v-model="button.country" v-if="button.type === 'PHONE_NUMBER'" :name="$t('Country')" :type="'text'" :class="'sm:col-span-2'" :labelClass="'mb-0'"/>
                            <FormInput v-model="button.phone_number" v-if="button.type === 'PHONE_NUMBER'" :name="$t('Phone number')" :type="'text'" :class="'sm:col-span-2'" :labelClass="'mb-0'"/>
                            <FormInput v-model="button.example" v-if="button.type === 'copy_code'" :name="$t('Sample code')" :type="'text'" :class="'w-full'" :labelClass="'mb-0'"/>
                        </div>
                    </div>
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
        <div class="md:w-[50%] py-20 px-4 md:px-20 overflow-y-auto" style="background-image: url('/images/whatsapp-bg-02.png');">
            <div>
                <!-- Show template preview for template campaigns -->
                <WhatsappTemplate v-if="isTemplateMode" :parameters="form" :visible="form.template ? true : false"/>

                <!-- Show direct message preview for direct campaigns -->
                <div v-else-if="isDirectMode">
                    <div class="mr-auto rounded-lg rounded-tl-none my-1 p-1 text-sm bg-white flex flex-col relative speech-bubble-left w-[25em]">
                        <div v-if="previewData.header_type !== 'text'" class="mb-4 bg-[#ccd0d5] flex justify-center py-8 rounded">
                            <img v-if="previewData.header_type === 'image'" :src="'/images/image-placeholder.png'">
                            <img v-if="previewData.header_type === 'video'" :src="'/images/video-placeholder.png'">
                            <img v-if="previewData.header_type === 'document'" :src="'/images/document-placeholder.png'">
                        </div>
                        <h2 v-else class="text-gray-700 text-sm mb-1 px-2 normal-case whitespace-pre-wrap">{{ previewData.header_text }}</h2>
                        <p class="px-2 normal-case whitespace-pre-wrap">{{ previewData.body_text }}</p>
                        <div class="text-[#8c8c8c] mt-1 px-2">
                            <span class="text-[13px]">{{ previewData.footer_text }}</span>
                            <span class="text-right text-xs leading-none float-right" :class="previewData.footer_text ? 'mt-2' : ''">9:15</span>
                        </div>
                    </div>
                    <div v-if="previewData.buttons && previewData.buttons.length > 0" class="mr-auto text-sm text-[#00a5f4] flex flex-col relative max-w-[25em]">
                        <div v-for="(button, index) in previewData.buttons" :key="index" class="flex justify-center items-center space-x-2 rounded-lg bg-white h-10 my-[0.1em]">
                            <span>
                                <svg v-if="button.type === 'copy_code'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M19 21H8V7h11m0-2H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2m-3-4H4a2 2 0 0 0-2 2v14h2V3h12V1Z"/></svg>
                                <svg v-else-if="button.type === 'PHONE_NUMBER'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><g fill="none"><path fill="currentColor" d="M20 16v4c-2.758 0-5.07-.495-7-1.325c-3.841-1.652-6.176-4.63-7.5-7.675C4.4 8.472 4 5.898 4 4h4l1 4l-3.5 3c1.324 3.045 3.659 6.023 7.5 7.675L16 15l4 1z"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 18.675c1.93.83 4.242 1.325 7 1.325v-4l-4-1l-3 3.675zm0 0C9.159 17.023 6.824 14.045 5.5 11m0 0C4.4 8.472 4 5.898 4 4h4l1 4l-3.5 3z"/></g></svg>
                                <img v-else-if="button.type === 'URL'" :src="'/images/icons/link.png'" class="h-4">
                                <img v-else :src="'/images/icons/reply.png'" class="h-4">
                            </span>
                            <span>{{ button.text }}</span>
                        </div>
                    </div>
                </div>

                <!-- Show empty state for direct campaigns without content -->
                <div v-else-if="isDirectMode && !previewData.body_text && !previewData.header_text && !previewData.header_media" class="bg-white rounded-lg shadow-lg max-w-sm mx-auto p-8 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" class="mx-auto text-gray-400 mb-4"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    <p class="text-gray-500 text-sm">{{ $t('Start typing your message to see a preview') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>