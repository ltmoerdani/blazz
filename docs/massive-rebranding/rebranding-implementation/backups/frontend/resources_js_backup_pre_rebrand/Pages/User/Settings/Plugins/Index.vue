<template>
    <SettingLayout :modules="props.modules">
        <div class="md:h-[90vh]">
            <div class="flex justify-center items-center mb-8">
                <div class="md:w-[60em]">
                    <div class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-2">
                        <div class="flex px-4 pt-2 pb-4">
                            <div>
                                <h2 class="text-[17px]">{{ $t('Plugins Section') }}</h2>
                                <span class="flex items-center mt-1">
                                    <svg class="mr-2" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    {{ $t('Enable or disable plugins to enhance your business operations.') }}
                                </span>
                            </div>
                        </div>
                        <div class="max-w-7xl mx-auto">
                            <div class="overflow-hidden sm:rounded-lg">
                                <div class="p-6">
                                    <div v-if="plugins.length === 0" class="text-center text-gray-500">
                                        No plugins available
                                    </div>
                                    
                                    <div v-else class="space-y-6">
                                        <div v-for="plugin in plugins" :key="plugin.id" 
                                            class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-grow">
                                                    <div class="flex items-center space-x-3">
                                                        <img :src="plugin.icon" :alt="plugin.name" class="w-8 h-8">
                                                        <h3 class="text-lg font-medium text-gray-900">
                                                            {{ plugin.name }}
                                                        </h3>
                                                        <span :class="[
                                                            'px-2 py-1 text-xs rounded-full',
                                                            plugin.status === 'connected' ? 'bg-green-100 text-green-800' : 
                                                            plugin.status === 'not_connected' ? 'bg-gray-100 text-gray-800' :
                                                            'bg-yellow-100 text-yellow-800'
                                                        ]">
                                                            {{ plugin.status === 'connected' ? 'Connected' : 
                                                            plugin.status === 'not_connected' ? 'Not Connected' : 
                                                            'Setup Required' }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-sm text-gray-500">
                                                        {{ plugin.description }}
                                                    </p>
                                                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                                        <span>Version {{ plugin.version }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-4">
                                                    <template v-if="plugin.type === 'woocommerce'">
                                                        <button
                                                            v-if="!plugin.installed"
                                                            @click="downloadPlugin(plugin)"
                                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                                                        >
                                                            <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="#000" stroke-linecap="round" stroke-width="1.5"><path d="M8 22h8c2.828 0 4.243 0 5.121-.878C22 20.242 22 18.829 22 16v-1c0-2.828 0-4.242-.879-5.121c-.768-.768-1.946-.865-4.121-.877m-10 0c-2.175.012-3.353.109-4.121.877C2 10.758 2 12.172 2 15v1c0 2.829 0 4.243.879 5.122c.3.3.662.497 1.121.627"/><path stroke-linejoin="round" d="M12 2v13m0 0l-3-3.5m3 3.5l3-3.5"/></g></svg>
                                                            Download Plugin
                                                        </button>
                                                        <button
                                                            v-else
                                                            @click="configurePlugin(plugin)"
                                                            class="text-sm text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            Configure
                                                        </button>
                                                    </template>
                                                    <template v-else-if="plugin.type === 'shopify'">
                                                        <button
                                                            @click="connectShopify(plugin)"
                                                            :class="[
                                                                'inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium',
                                                                plugin.status === 'connected' 
                                                                    ? 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'
                                                                    : 'border-transparent text-white bg-indigo-600 hover:bg-indigo-700'
                                                            ]"
                                                        >
                                                            {{ plugin.status === 'connected' ? 'Manage Connection' : 'Connect Shopify' }}
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>

<script setup>
import { ref } from 'vue';
import SettingLayout from "./../Layout.vue";

const props = defineProps({
    modules: {
        type: Array,
        required: true
    },
    plugins: {
        type: Array,
        required: true,
        default: () => ([
            {
                id: 'woocommerce',
                name: 'WooCommerce',
                type: 'woocommerce',
                description: 'Connect your WooCommerce store to sync products, orders, and customers.',
                version: '1.0.0',
                author: 'Your Company',
                icon: '/icons/woocommerce.svg',
                installed: false,
                status: 'not_connected'
            },
            {
                id: 'shopify',
                name: 'Shopify',
                type: 'shopify',
                description: 'Connect your Shopify store to sync products, orders, and customers.',
                version: '1.0.0',
                author: 'Your Company',
                icon: '/icons/shopify.svg',
                status: 'not_connected'
            }
        ])
    }
})

const downloadPlugin = async (plugin) => {
    // Implementation for downloading WooCommerce plugin
    try {
        // Add download logic here
        plugin.installed = true
    } catch (error) {
        // Handle error
    }
}

const connectShopify = async (plugin) => {
    // Implementation for Shopify connection
    if (plugin.status === 'connected') {
        window.location.href = `/settings/plugins/${plugin.id}/manage`
    } else {
        window.location.href = `/settings/plugins/${plugin.id}/connect`
    }
}

const configurePlugin = (plugin) => {
    window.location.href = `/settings/plugins/${plugin.id}/configure`
}
</script> 