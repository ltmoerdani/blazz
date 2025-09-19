<template>
    <div class="flex h-screen justify-center">
        <div class="flex justify-center">
            <div class="mt-20">
                <div class="flex justify-center mb-5">
                    <div>
                        <h4 class="text-2xl mb-2 text-center">Blazz</h4>
                        <h1 class="text-2xl text-center">{{ $t('Proceed to update') }}</h1>
                    </div>
                </div>
                <div v-if="step === null" class="md:w-[20em] p-2">
                    <div class="bg-blue-50 p-4 border-l-[3px] border-blue-600 mb-4">
                        <h4 class="text-center text-xl mb-2">{{ $t('Ready to Update') }}</h4>
                        <p class="text-sm text-center text-gray-600">{{ $t('Purchase code validation has been disabled for security. Click proceed to continue with the update.') }}</p>
                    </div>
                    <div class="mt-2">
                        <button v-if="!isLoading" @click="startUpdate" type="button" class="mt-4 rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm w-full">{{ $t('Proceed to Update') }}</button>
                        <button v-else type="button" class="mt-4 rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm w-full flex justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        </button>
                    </div>
                </div>
                <div v-else-if="step === 'migrate'" class="md:w-[30em] p-2">
                    <div class="bg-slate-200 p-2 border-l-[3px] border-red-800 text-sm">
                        <p>{{ $t('When you click proceed, your script will be updated automatically! Do not navigate away from the page while this process is ongoing.') }}</p>
                    </div>
                    <form @submit.prevent="runMigration()">
                        <div class="flex justify-center gap-x-2">
                            <button v-if="!isLoading" type="submit" class="w-[10em] mt-4 rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm">{{ $t('Proceed') }}</button>
                            <button v-else type="button" class="w-[10em] mt-4 rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm flex justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            </button>
                        </div>
                    </form>
                </div>
                <div v-else-if="step === 'finish'" class="md:w-[30em] p-2">
                    <p class="text-center text-xl mb-4">{{ $t('Update Successfull!') }}</p>
                    <div class="bg-green-50 p-2 border-l-[3px] border-green-800 mb-2">
                        <p>{{ $t('The application has been updated successfully!') }}</p>
                    </div>
                    <div class="bg-slate-100 rounded p-4">
                        <div class="bg-slate-200 p-2 rounded-md mt-2">
                            <p class="mb-2">{{ $t('Website Url') }}</p>
                            <div class="text-sm">
                                <div class="flex gap-x-1">
                                    <Link href="/" class="underline">{{ props.path }}</Link>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between gap-x-2">
                        <Link href="/" class="mt-4 rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm w-full text-center">{{ $t('Proceed To Main Site') }}</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
    import { Link } from "@inertiajs/vue3";
    import { defineProps, ref } from 'vue';
    import axios from 'axios';

    const props = defineProps(['path']);
    const step = ref(null);
    const isLoading = ref(false);

    const runMigration = async () => {
        isLoading.value = true;

        const response = await axios.post('/update', {});

        //console.log(response.data)
        if(response.data.statusCode === 200){
            step.value = 'finish';
        }
    };

    const startUpdate = () => {
        step.value = 'migrate';
    };
</script>