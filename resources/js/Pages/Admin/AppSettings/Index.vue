<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    settings: Object,
});

const logoPreview = ref(props.settings?.logo_url || null);
const fileInput = ref(null);
const isTestingSmtp = ref(false);
const smtpTestMessage = ref(null);
const smtpTestSuccess = ref(false);
const showTestModal = ref(false);
const testRecipientEmail = ref('');

const alertModal = ref({
    show: false,
    title: '',
    message: ''
});

const form = useForm({
    name: props.settings?.name || '',
    logo: null,
    currency: props.settings?.currency || 'EUR',
    smtp_host: props.settings?.smtp_host || '',
    smtp_port: props.settings?.smtp_port || '',
    smtp_username: props.settings?.smtp_username || '',
    smtp_password: '', // leave blank intentionally so user must explicitly replace it
    smtp_encryption: props.settings?.smtp_encryption || 'tls',
    smtp_from_address: props.settings?.smtp_from_address || '',
    smtp_from_name: props.settings?.smtp_from_name || '',
});

const openSmtpTestModal = () => {
    if (!form.smtp_host || !form.smtp_port || !form.smtp_from_address) {
        alertModal.value = {
            show: true,
            title: 'Missing Parameters',
            message: 'Please supply SMTP Host, Port, and Sender Address configuration data before initiating connectivity diagnostics.'
        };
        return;
    }
    testRecipientEmail.value = form.smtp_from_address;
    showTestModal.value = true;
};

const runSmtpTest = async () => {
    if (!testRecipientEmail.value) return;

    isTestingSmtp.value = true;
    smtpTestMessage.value = null;
    showTestModal.value = false; // close modal, but run background test

    try {
        const response = await axios.post(route('admin.app-settings.test-smtp'), {
            smtp_host: form.smtp_host,
            smtp_port: form.smtp_port,
            smtp_username: form.smtp_username,
            smtp_password: form.smtp_password,
            smtp_encryption: form.smtp_encryption,
            smtp_from_address: form.smtp_from_address,
            smtp_from_name: form.smtp_from_name,
            test_email: testRecipientEmail.value,
        });

        smtpTestSuccess.value = true;
        smtpTestMessage.value = response.data.message;
    } catch (error) {
        smtpTestSuccess.value = false;
        smtpTestMessage.value = error.response?.data?.message || 'An unexpected network error occurred.';
    } finally {
        isTestingSmtp.value = false;
    }
};

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.logo = file;
        const reader = new FileReader();
        reader.onload = (event) => {
            logoPreview.value = event.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const submitUpdate = () => {
    // Since we are uploading a file, we submit as POST
    form.post(route('admin.app-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // success handled by Inertia flash messages
        }
    });
};

const currencies = [
    { code: 'EUR', symbol: '€', name: 'Euro (EUR)' },
    { code: 'MYR', symbol: 'RM', name: 'Malaysian Ringgit (MYR)' },
    { code: 'USD', symbol: '$', name: 'US Dollar (USD)' },
    { code: 'IDR', symbol: 'Rp', name: 'Indonesian Rupiah (IDR)' },
    { code: 'SGD', symbol: 'S$', name: 'Singapore Dollar (SGD)' },
    { code: 'GBP', symbol: '£', name: 'British Pound (GBP)' },
    { code: 'AUD', symbol: 'A$', name: 'Australian Dollar (AUD)' },
];
</script>

<template>
    <Head title="App Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">App Configuration</h2>
                <p class="text-sm text-gray-500 mt-1">Configure application branding, owner information, and active transactional currency.</p>
            </div>
        </template>

        <div class="max-w-3xl bg-white border border-gray-100 rounded-2xl p-8 shadow-sm">
            <form @submit.prevent="submitUpdate" class="space-y-6">
                <!-- App Owner Name -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Company / Owner Name</label>
                    <input 
                        type="text" 
                        v-model="form.name" 
                        required
                        placeholder="e.g. PT. Metro Pacific Tbk"
                        class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-3"
                    />
                    <span v-if="form.errors.name" class="text-xs text-red-500 mt-1 block">{{ form.errors.name }}</span>
                </div>

                <!-- Currency Selection -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Primary Currency</label>
                    <select 
                        v-model="form.currency" 
                        required
                        class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-3"
                    >
                        <option v-for="curr in currencies" :key="curr.code" :value="curr.code">
                            {{ curr.name }} - Symbol: {{ curr.symbol }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1.5">Replacing the currency will instantly update all prices and invoices on storefront catalog, cart, and dashboard views.</p>
                    <span v-if="form.errors.currency" class="text-xs text-red-500 mt-1 block">{{ form.errors.currency }}</span>
                </div>

                <!-- App Logo Upload Dropzone -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Application Logo</label>
                    <div 
                        @click="triggerFileInput"
                        class="border-2 border-dashed border-gray-200 rounded-2xl p-6 hover:border-[#e96a25] hover:bg-gray-50/50 cursor-pointer transition flex flex-col items-center justify-center text-center"
                    >
                        <input 
                            type="file" 
                            ref="fileInput" 
                            @change="onFileChange" 
                            accept="image/*" 
                            class="hidden" 
                        />
                        
                        <!-- Logo Preview / Dropzone info -->
                        <div v-if="logoPreview" class="relative group">
                            <img :src="logoPreview" alt="Logo Preview" class="max-h-24 object-contain rounded-lg mb-2" />
                            <div class="absolute inset-0 bg-black/40 rounded-lg opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold">
                                Change Logo
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center">
                            <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400 mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-[#1a2b4c]">Click to Upload Logo</span>
                            <span class="text-xs text-gray-400 mt-1">Supports PNG, JPEG, JPG, SVG (Max: 2MB)</span>
                        </div>
                    </div>
                    <span v-if="form.errors.logo" class="text-xs text-red-500 mt-1 block">{{ form.errors.logo }}</span>
                </div>

                <!-- Email / SMTP Section -->
                <div class="pt-8 mt-8 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <h3 class="text-lg font-bold text-[#1a2b4c]">Email Service (SMTP)</h3>
                        </div>
                        <button 
                            type="button" 
                            @click="openSmtpTestModal"
                            :disabled="isTestingSmtp"
                            class="text-xs font-bold px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg border border-blue-100 hover:bg-blue-100 transition-colors flex items-center space-x-1.5 disabled:opacity-50"
                        >
                            <span v-if="isTestingSmtp" class="animate-spin w-3 h-3 border-2 border-blue-600 border-t-transparent rounded-full"></span>
                            <span>{{ isTestingSmtp ? 'Verifying Connection...' : 'Test Connection' }}</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-4">Overrides system settings to connect your custom outbound mail server.</p>

                    <!-- Live Diagnostic Results -->
                    <div v-if="smtpTestMessage" 
                         :class="[smtpTestSuccess ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700']"
                         class="mb-6 text-xs p-4 rounded-xl border flex items-start space-x-3 shadow-sm transition-all duration-300">
                         <div :class="[smtpTestSuccess ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600']" class="p-1.5 rounded-full shrink-0 flex items-center justify-center">
                             <svg v-if="smtpTestSuccess" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                             <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                         </div>
                         <div class="flex-1 pt-0.5">
                             <p class="font-bold mb-0.5">{{ smtpTestSuccess ? 'Delivery Success!' : 'Diagnostic Error Log' }}</p>
                             <p class="font-mono leading-relaxed whitespace-pre-wrap break-all bg-white/30 p-2 rounded-lg mt-1">{{ smtpTestMessage }}</p>
                         </div>
                         <button type="button" @click="smtpTestMessage = null" class="text-lg opacity-60 hover:opacity-100 transition px-1 leading-none font-medium">&times;</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                        <!-- Host -->
                        <div class="md:col-span-2 lg:col-span-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">SMTP Host</label>
                            <input 
                                type="text" 
                                v-model="form.smtp_host" 
                                placeholder="e.g. mailhog, smtp.sendgrid.net"
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            />
                            <span v-if="form.errors.smtp_host" class="text-xs text-red-500 mt-1 block">{{ form.errors.smtp_host }}</span>
                        </div>

                        <!-- Port -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Port</label>
                            <input 
                                type="number" 
                                v-model="form.smtp_port" 
                                placeholder="587"
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            />
                            <span v-if="form.errors.smtp_port" class="text-xs text-red-500 mt-1 block">{{ form.errors.smtp_port }}</span>
                        </div>

                        <!-- Encryption -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Encryption</label>
                            <select 
                                v-model="form.smtp_encryption" 
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            >
                                <option value="">None</option>
                                <option value="tls">TLS (Recommend)</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>

                        <!-- User -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Username</label>
                            <input 
                                type="text" 
                                v-model="form.smtp_username" 
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            />
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Password</label>
                            <input 
                                type="password" 
                                v-model="form.smtp_password" 
                                placeholder="••••••••"
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            />
                            <p class="text-[10px] text-gray-400 mt-1 font-medium">Leave blank to maintain your existing encrypted password.</p>
                        </div>

                        <!-- Sender Email -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Sender Address</label>
                            <input 
                                type="email" 
                                v-model="form.smtp_from_address" 
                                placeholder="no-reply@mycompany.com"
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            />
                            <span v-if="form.errors.smtp_from_address" class="text-xs text-red-500 mt-1 block">{{ form.errors.smtp_from_address }}</span>
                        </div>

                        <!-- Sender Name -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1.5">Sender Display Name</label>
                            <input 
                                type="text" 
                                v-model="form.smtp_from_name" 
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                            />
                        </div>
                    </div>
                </div>

                <!-- Form Controls -->
                <div class="pt-6 border-t border-gray-100 flex justify-end">
                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="inline-flex items-center space-x-2 bg-gradient-to-r from-[#e96a25] to-[#d55e1d] text-white font-bold text-sm px-6 py-3 rounded-xl shadow-md hover:shadow-lg hover:from-[#d55e1d] hover:to-[#b84a14] transition duration-200 disabled:opacity-50"
                    >
                        <span>Save Configuration</span>
                    </button>
                </div>
            </form>
        </div>

        <Modal :show="showTestModal" @close="showTestModal = false" maxWidth="md">
            <div class="p-6 bg-white rounded-2xl overflow-hidden">
                <h2 class="text-lg font-bold text-[#1a2b4c] flex items-center">
                    <svg class="w-5 h-5 mr-2 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Verify Mailer Integrity
                </h2>
                <p class="mt-2 text-sm text-gray-500">Input an external target address to complete a synchronous SMTP dispatch validation.</p>
                
                <div class="mt-5">
                    <label class="block text-xs font-bold text-[#1a2b4c] mb-1.5">Recipient Address</label>
                    <input 
                        v-model="testRecipientEmail"
                        type="email" 
                        @keyup.enter="runSmtpTest"
                        placeholder="you@company.com"
                        class="w-full text-sm bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-gray-700 focus:bg-white focus:border-[#e96a25] focus:ring-2 focus:ring-[#e96a25]/20 transition-all placeholder:text-gray-400 outline-none"
                    />
                </div>

                <div class="mt-8 flex items-center justify-end space-x-3 border-t border-gray-50 pt-4">
                    <button 
                        type="button" 
                        @click="showTestModal = false" 
                        class="px-4 py-2 text-xs font-bold text-gray-500 hover:text-gray-700 transition-colors rounded-lg"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        @click="runSmtpTest"
                        :disabled="!testRecipientEmail"
                        class="px-6 py-2.5 text-xs font-bold text-white bg-[#1a2b4c] rounded-xl hover:bg-[#e96a25] transition-all shadow-md disabled:opacity-50 active:scale-95"
                    >
                        Send Validation Packet
                    </button>
                </div>
            </div>
        </Modal>

        <!-- App Alert Modal -->
        <ConfirmationModal
            :show="alertModal.show"
            :title="alertModal.title"
            :message="alertModal.message"
            type="warning"
            confirmLabel="Understood"
            :hide-cancel="true"
            @confirm="alertModal.show = false"
            @close="alertModal.show = false"
        />
    </AuthenticatedLayout>
</template>
