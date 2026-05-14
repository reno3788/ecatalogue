<script setup>
import Modal from './Modal.vue';
import SecondaryButton from './SecondaryButton.vue';
import DangerButton from './DangerButton.vue';
import PrimaryButton from './PrimaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirm Action',
    },
    message: {
        type: String,
        default: 'Are you sure you want to proceed?',
    },
    confirmLabel: {
        type: String,
        default: 'Confirm',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    maxWidth: {
        type: String,
        default: 'md',
    },
    type: {
        type: String,
        default: 'danger', // danger, primary, warning, success
    },
    hideCancel: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['close', 'confirm']);

const close = () => {
    emit('close');
};

const confirm = () => {
    emit('confirm');
};
</script>

<template>
    <Modal :show="show" :maxWidth="maxWidth" @close="close">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div 
                    :class="[
                        'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10',
                        type === 'danger' ? 'bg-red-100' : 
                        type === 'warning' ? 'bg-amber-100' :
                        type === 'success' ? 'bg-emerald-100' : 'bg-blue-100'
                    ]"
                >
                    <svg v-if="type === 'danger'" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <svg v-else-if="type === 'warning'" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <svg v-else-if="type === 'success'" class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-lg font-semibold text-gray-900 tracking-tight">
                        {{ title }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm font-medium text-gray-500 leading-relaxed">
                            {{ message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
            <DangerButton 
                v-if="type === 'danger'"
                class="w-full sm:w-auto justify-center rounded-xl"
                @click="confirm"
            >
                {{ confirmLabel }}
            </DangerButton>
            <PrimaryButton 
                v-else-if="type === 'warning'"
                class="w-full sm:w-auto justify-center rounded-xl !bg-amber-600 hover:!bg-amber-700 text-white"
                @click="confirm"
            >
                {{ confirmLabel }}
            </PrimaryButton>
            <PrimaryButton 
                v-else-if="type === 'success'"
                class="w-full sm:w-auto justify-center rounded-xl !bg-emerald-600 hover:!bg-emerald-700 text-white"
                @click="confirm"
            >
                {{ confirmLabel }}
            </PrimaryButton>
            <PrimaryButton 
                v-else
                class="w-full sm:w-auto justify-center rounded-xl !bg-[#1a2b4c] hover:!bg-[#111d33]"
                @click="confirm"
            >
                {{ confirmLabel }}
            </PrimaryButton>

            <SecondaryButton v-if="!hideCancel" class="mt-3 sm:mt-0 w-full sm:w-auto justify-center rounded-xl" @click="close">
                {{ cancelLabel }}
            </SecondaryButton>
        </div>
    </Modal>
</template>
