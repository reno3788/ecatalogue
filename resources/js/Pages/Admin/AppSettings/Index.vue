<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    settings: Object,
    carriers: Array,
});

const logoPreview = ref(props.settings?.logo_url || null);
const fileInput = ref(null);
const isTestingSmtp = ref(false);
const smtpTestMessage = ref(null);
const smtpTestSuccess = ref(false);
const showTestModal = ref(false);
const testRecipientEmail = ref('');

const activeTab = ref('general');

const alertModal = ref({
    show: false,
    title: '',
    message: ''
});

// General Settings Form
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

// Carrier CRUD Form
const carrierForm = useForm({
    id: null,
    name: '',
    tracking_url_pattern: ''
});

const isEditingCarrier = ref(false);
const showCarrierModal = ref(false);
const showDeleteCarrierModal = ref(false);
const carrierToDelete = ref(null);

const openCreateCarrier = () => {
    isEditingCarrier.value = false;
    carrierForm.clearErrors();
    carrierForm.id = null;
    carrierForm.name = '';
    carrierForm.tracking_url_pattern = '';
    showCarrierModal.value = true;
};

const openEditCarrier = (carrier) => {
    isEditingCarrier.value = true;
    carrierForm.clearErrors();
    carrierForm.id = carrier.id;
    carrierForm.name = carrier.name;
    carrierForm.tracking_url_pattern = carrier.tracking_url_pattern || '';
    showCarrierModal.value = true;
};

const submitCarrier = () => {
    if (isEditingCarrier.value) {
        carrierForm.put(route('admin.carriers.update', carrierForm.id), {
            onSuccess: () => {
                showCarrierModal.value = false;
                carrierForm.reset();
            }
        });
    } else {
        carrierForm.post(route('admin.carriers.store'), {
            onSuccess: () => {
                showCarrierModal.value = false;
                carrierForm.reset();
            }
        });
    }
};

const confirmDeleteCarrier = (carrier) => {
    carrierToDelete.value = carrier;
    showDeleteCarrierModal.value = true;
};

const deleteCarrier = () => {
    if (!carrierToDelete.value) return;
    carrierForm.delete(route('admin.carriers.destroy', carrierToDelete.value.id), {
        onSuccess: () => {
            showDeleteCarrierModal.value = false;
            carrierToDelete.value = null;
        }
    });
};

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
                <p class="text-sm text-gray-500 mt-1">Configure application branding, SMTP mailers, transactional currency, and active shipping carriers.</p>
            </div>
        </template>

        <div class="max-w-4xl space-y-6">
            <!-- Dynamic Premium Tab Selector -->
            <div class="flex p-1 bg-gray-100/80 backdrop-blur-md rounded-2xl border border-gray-200/50 self-start md:self-auto shadow-sm max-w-sm mb-6">
                <button 
                    type="button"
                    @click="activeTab = 'general'" 
                    :class="activeTab === 'general' 
                        ? 'bg-white text-[#e96a25] shadow-md border-gray-100 font-bold scale-[1.02]' 
                        : 'text-gray-500 hover:text-gray-800 font-semibold'"
                    class="flex-1 py-2.5 px-4 text-xs rounded-xl transition-all duration-200 flex items-center justify-center gap-2 border border-transparent"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>General Settings</span>
                </button>
                <button 
                    type="button"
                    @click="activeTab = 'carriers'" 
                    :class="activeTab === 'carriers' 
                        ? 'bg-white text-[#e96a25] shadow-md border-gray-100 font-bold scale-[1.02]' 
                        : 'text-gray-500 hover:text-gray-800 font-semibold'"
                    class="flex-1 py-2.5 px-4 text-xs rounded-xl transition-all duration-200 flex items-center justify-center gap-2 border border-transparent"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    <span>Shipping Carriers</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#e96a25]/10 text-[#e96a25]">
                        {{ carriers.length }}
                    </span>
                </button>
            </div>

            <!-- Tab 1: General Settings Form -->
            <div v-if="activeTab === 'general'" class="bg-white border border-gray-100 rounded-2xl p-8 shadow-sm transition-all duration-300">
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

                            <!-- Username -->
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
                            class="inline-flex items-center space-x-2 bg-[#1a2b4c] text-white font-bold text-sm px-6 py-3 rounded-xl shadow-md hover:shadow-lg hover:bg-[#e96a25] transition duration-200 disabled:opacity-50"
                        >
                            <span>Save Configuration</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Shipping Carriers Management -->
            <div v-else-if="activeTab === 'carriers'" class="bg-white border border-gray-100 rounded-2xl p-8 shadow-sm transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-[#1a2b4c]">Configured Carriers</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Manage the default dropdown list of shipping providers and external tracking endpoints.</p>
                    </div>
                    <button 
                        type="button" 
                        @click="openCreateCarrier"
                        class="inline-flex items-center space-x-1.5 bg-[#1a2b4c] text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-sm hover:bg-[#e96a25] hover:shadow-md transition duration-200"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Carrier</span>
                    </button>
                </div>

                <!-- Guidance Info Box -->
                <div class="bg-[#e96a25]/5 border border-[#e96a25]/15 rounded-2xl p-4 flex items-start space-x-3 mb-6">
                    <div class="p-1.5 bg-[#e96a25]/10 text-[#e96a25] rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-xs text-gray-700 leading-relaxed">
                        <p class="font-bold text-[#1a2b4c] mb-0.5">Understanding Tracking URL Templates</p>
                        <p>You can define external tracking links for each carrier. Always include the exact placeholder <code class="font-mono bg-white/70 border border-[#e96a25]/15 px-1 py-0.5 rounded text-[#e96a25] font-black">{tracking_number}</code> in the template pattern. This placeholder will automatically resolve to the target tracking code when shown to customers.</p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="carriers.length === 0" class="border border-dashed border-gray-200 rounded-2xl p-12 text-center flex flex-col items-center justify-center">
                    <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-2xl flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-bold text-[#1a2b4c]">No Shipping Carriers Configured</h4>
                    <p class="text-xs text-gray-400 mt-1 max-w-xs leading-relaxed">Configuring carriers allows suppliers to easily attach tracking credentials to orders promoted to "Shipped".</p>
                </div>

                <!-- Carriers Table List -->
                <div v-else class="overflow-x-auto border border-gray-100 rounded-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 text-[10px] uppercase font-bold tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="px-6 py-3.5">Carrier Name</th>
                                <th class="px-6 py-3.5">Tracking Link Template</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <tr v-for="carrier in carriers" :key="carrier.id" class="hover:bg-gray-50/30 transition-colors">
                                <td class="px-6 py-4 font-bold text-[#1a2b4c]">
                                    {{ carrier.name }}
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-500 max-w-md truncate">
                                    <span v-if="carrier.tracking_url_pattern">
                                        {{ carrier.tracking_url_pattern.split('{tracking_number}')[0] }}<span class="text-[#e96a25] font-black underline bg-[#e96a25]/5 px-1 py-0.5 rounded">{tracking_number}</span>{{ carrier.tracking_url_pattern.split('{tracking_number}')[1] }}
                                    </span>
                                    <span v-else class="text-gray-300 italic font-sans">No tracking template defined</span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button 
                                        type="button" 
                                        @click="openEditCarrier(carrier)"
                                        class="p-1.5 text-gray-400 hover:text-blue-600 transition rounded-lg hover:bg-blue-50"
                                        title="Edit Carrier"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h10a2 2 0 002-2v-5M18.364 4.982a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L18.364 4.982z" />
                                        </svg>
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="confirmDeleteCarrier(carrier)"
                                        class="p-1.5 text-gray-400 hover:text-red-600 transition rounded-lg hover:bg-red-50"
                                        title="Delete Carrier"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carrier Add/Edit Modal -->
        <Modal :show="showCarrierModal" @close="showCarrierModal = false" maxWidth="lg">
            <div class="p-6 bg-white rounded-2xl overflow-hidden">
                <h2 class="text-lg font-bold text-[#1a2b4c] flex items-center mb-1">
                    <svg class="w-5 h-5 mr-2 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    <span>{{ isEditingCarrier ? 'Modify Shipping Carrier' : 'Add New Shipping Carrier' }}</span>
                </h2>
                <p class="text-xs text-gray-500 mb-5">Define the identity and live diagnostic tracking link for this carrier.</p>
                
                <form @submit.prevent="submitCarrier" class="space-y-4">
                    <!-- Carrier Name -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Carrier Name</label>
                        <input 
                            v-model="carrierForm.name"
                            type="text" 
                            required
                            placeholder="e.g. DHL Express, J&T Express, FedEx"
                            class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-3"
                        />
                        <span v-if="carrierForm.errors.name" class="text-xs text-red-500 mt-1 block">{{ carrierForm.errors.name }}</span>
                    </div>

                    <!-- Tracking URL Template -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Tracking URL Template (Optional)</label>
                        <input 
                            v-model="carrierForm.tracking_url_pattern"
                            type="text" 
                            placeholder="https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}"
                            class="w-full text-sm rounded-xl font-mono border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-3"
                        />
                        <span v-if="carrierForm.errors.tracking_url_pattern" class="text-xs text-red-500 mt-1 block">{{ carrierForm.errors.tracking_url_pattern }}</span>
                        
                        <div class="mt-2 bg-gray-50 border border-gray-100 rounded-xl p-3 text-[11px] text-gray-500 leading-relaxed">
                            <p class="font-bold text-[#1a2b4c] mb-0.5">Quick Examples:</p>
                            <ul class="list-disc pl-4 space-y-0.5 font-mono">
                                <li><strong>FedEx:</strong> https://www.fedex.com/fedextrack/?tracknumbers={tracking_number}</li>
                                <li><strong>DHL:</strong> https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}</li>
                                <li><strong>JNE (ID):</strong> https://www.jne.co.id/en/tracking/trace/{tracking_number}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-50 flex items-center justify-end space-x-3 mt-6">
                        <button 
                            type="button" 
                            @click="showCarrierModal = false" 
                            class="px-4 py-2 text-xs font-bold text-gray-500 hover:text-gray-700 transition-colors rounded-lg"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="carrierForm.processing"
                            class="px-6 py-2.5 text-xs font-bold text-white bg-[#1a2b4c] rounded-xl hover:bg-[#e96a25] transition-all shadow-md disabled:opacity-50 active:scale-95"
                        >
                            {{ isEditingCarrier ? 'Save Changes' : 'Register Carrier' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Carrier Delete Confirmation -->
        <ConfirmationModal
            :show="showDeleteCarrierModal"
            title="Delete Shipping Carrier"
            :message="`Are you absolutely sure you want to delete '${carrierToDelete?.name}'? Orders associated with this carrier will maintain their records, but this option will no longer be select-able in new fulfillment tasks.`"
            type="danger"
            confirmLabel="Permanently Delete"
            cancelLabel="Cancel"
            @confirm="deleteCarrier"
            @close="showDeleteCarrierModal = false"
        />

        <!-- SMTP Test Modal -->
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
