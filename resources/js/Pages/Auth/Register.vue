<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
    company_address: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Account" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-[#1a2b4c]">Register</h2>
            <p class="text-sm text-gray-500 mt-1">Register today to start streamlined procurement.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Personal Details section -->
            <div>
                <InputLabel for="name" value="Full Name" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Enter your full name"
                />
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email Address" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="Enter professional email"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- Company Info section -->
            <div class="mt-6 border-t border-gray-100 pt-5">
                <div class="flex items-center mb-4">
                    <div class="w-8 border-t border-[#e96a25]/30"></div>
                    <span class="px-3 text-xs font-black uppercase tracking-widest text-[#e96a25]">Company Validation</span>
                    <div class="flex-1 border-t border-[#e96a25]/10"></div>
                </div>
                
                <div>
                    <InputLabel for="company_name" value="Legal Entity Name" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                    <TextInput
                        id="company_name"
                        type="text"
                        class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                        v-model="form.company_name"
                        required
                        placeholder="e.g. PT. Global Synergy"
                    />
                    <InputError class="mt-1.5" :message="form.errors.company_name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="company_address" value="Registered Address" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                    <textarea
                        id="company_address"
                        class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all min-h-[80px]"
                        v-model="form.company_address"
                        required
                        rows="3"
                        placeholder="Enter official company address"
                    ></textarea>
                    <InputError class="mt-1.5" :message="form.errors.company_address" />
                </div>
            </div>

            <div class="mt-6 border-t border-gray-100 pt-5">
                <div class="flex items-center mb-4">
                    <div class="w-8 border-t border-gray-200"></div>
                    <span class="px-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Security</span>
                    <div class="flex-1 border-t border-gray-100"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="password" value="Create Password" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirm" class="text-gray-700 font-semibold text-xs uppercase tracking-wider" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1.5 block w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition-all"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <button
                    type="submit"
                    class="w-full bg-[#e96a25] hover:bg-[#d85c1d] text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-orange-500/10 hover:shadow-orange-500/20 active:scale-[0.98] transition-all duration-150 flex items-center justify-center space-x-2"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mr-2"></span>
                    <span>Create My Account</span>
                </button>
            </div>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center text-sm">
            <span class="text-gray-500">Already registered? </span>
            <Link :href="route('login')" class="font-semibold text-[#e96a25] hover:text-[#d85c1d] transition-colors">
                Sign in here
            </Link>
        </div>
    </GuestLayout>
</template>
