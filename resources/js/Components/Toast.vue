<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success'); // 'success' | 'error' | 'info'

const showToast = (msg, msgType = 'success') => {
    message.value = msg;
    type.value = msgType;
    visible.value = true;
    
    setTimeout(() => {
        visible.value = false;
    }, 4500);
};

// Watch for flash messages in the page props
watch(() => page.props.flash, (flash) => {
    if (flash) {
        if (flash.success) {
            showToast(flash.success, 'success');
        } else if (flash.error) {
            showToast(flash.error, 'error');
        } else if (flash.message) {
            showToast(flash.message, 'info');
        }
    }
}, { deep: true, immediate: true });
</script>

<template>
    <div class="fixed bottom-5 right-5 z-50 pointer-events-none">
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="visible" class="max-w-sm w-full bg-white shadow-2xl rounded-xl pointer-events-auto overflow-hidden border border-gray-100 flex p-4 items-center space-x-3 backdrop-blur-md bg-white/95">
                <!-- Icon Success -->
                <div v-if="type === 'success'" class="bg-emerald-500/10 text-emerald-600 rounded-lg p-2 flex-shrink-0">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <!-- Icon Error -->
                <div v-else-if="type === 'error'" class="bg-rose-500/10 text-rose-600 rounded-lg p-2 flex-shrink-0">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <!-- Icon Info -->
                <div v-else class="bg-amber-500/10 text-amber-600 rounded-lg p-2 flex-shrink-0">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 leading-tight">
                        {{ type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Notification' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1 font-medium leading-normal">
                        {{ message }}
                    </p>
                </div>

                <button @click="visible = false" class="text-gray-400 hover:text-gray-600 focus:outline-none flex-shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition>
    </div>
</template>
