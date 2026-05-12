<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-[#1a2b4c]">Reset Password</h2>
            <p class="text-sm text-gray-500 mt-1">Forgot your password? No problem. Just let us know your email address and we will email you a password reset link.</p>
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Email Address" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Enter your registered email"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <button
                type="submit"
                class="w-full mt-6 bg-[#e96a25] hover:bg-[#d85c1d] text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-orange-500/10 hover:shadow-orange-500/20 active:scale-[0.98] transition-all duration-150 flex items-center justify-center space-x-2"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2"></span>
                <span>Send Reset Link</span>
            </button>
        </form>
    </GuestLayout>
</template>
