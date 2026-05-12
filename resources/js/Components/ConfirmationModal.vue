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
        default: 'danger', // danger, primary
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
                        type === 'danger' ? 'bg-red-100' : 'bg-blue-100'
                    ]"
                >
                    <svg v-if="type === 'danger'" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <svg v-else class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">
                        {{ title }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            {{ message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <DangerButton 
                v-if="type === 'danger'"
                class="sm:ml-3 w-full sm:w-auto justify-center"
                @click="confirm"
            >
                {{ confirmLabel }}
            </DangerButton>
            <PrimaryButton 
                v-else
                class="sm:ml-3 w-full sm:w-auto justify-center !bg-[#1a2b4c] hover:!bg-[#111d33]"
                @click="confirm"
            >
                {{ confirmLabel }}
            </PrimaryButton>

            <SecondaryButton class="mt-3 sm:mt-0 w-full sm:w-auto justify-center" @click="close">
                {{ cancelLabel }}
            </SecondaryButton>
        </div>
    </Modal>
</template>
