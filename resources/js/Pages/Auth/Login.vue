<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-[#1a2b4c]">Welcome back</h2>
            <p class="text-sm text-gray-500 mt-1">Please sign in to access the procurement portal.</p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
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
                    placeholder="Enter your email"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Password" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                    
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-semibold text-[#e96a25] hover:text-[#d85c1d] transition-colors"
                    >
                        Forgot password?
                    </Link>
                </div>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer group">
                    <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-gray-300 text-[#e96a25] focus:ring-[#e96a25]" />
                    <span class="ms-2 text-sm text-gray-600 group-hover:text-[#1a2b4c] transition-colors">Remember me</span>
                </label>
            </div>

            <button
                type="submit"
                class="w-full mt-6 bg-[#e96a25] hover:bg-[#d85c1d] text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-orange-500/10 hover:shadow-orange-500/20 active:scale-[0.98] transition-all duration-150 flex items-center justify-center space-x-2"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2"></span>
                <span>Sign In</span>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center text-sm">
            <span class="text-gray-500">Don't have an account? </span>
            <Link :href="route('register')" class="font-semibold text-[#e96a25] hover:text-[#d85c1d] transition-colors">
                Register here
            </Link>
        </div>
    </GuestLayout>
</template>
