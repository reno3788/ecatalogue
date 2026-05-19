<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    companies: Array,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.roles.includes('admin'));

const searchQuery = ref('');
const selectedCompany = ref(null);
const showDetailsModal = ref(false);
const showFormModal = ref(false);
const showDeleteModal = ref(false);
const companyToDelete = ref(null);

const defaultFormData = {
    id: null,
    name: '',
    abeta_id: '',
    status: 'Active',
    address: '',
    phone: '',
    contact_person: '',
    taxid: '',
    taxaddress: '',
    punchout_enabled: false,
    punchout_gateway: 'abeta',
    punchout_url: '',
    punchout_identity: '',
    punchout_shared_secret: '',
    bargaining_enabled: true,
};

const form = useForm({ ...defaultFormData });

// Derived Companies list filtering
const filteredCompanies = computed(() => {
    if (!searchQuery.value.trim()) return props.companies;
    const query = searchQuery.value.toLowerCase();
    return props.companies.filter(company => 
        (company.name && company.name.toLowerCase().includes(query)) ||
        (company.abeta_id && company.abeta_id.toLowerCase().includes(query)) ||
        (company.contact_person && company.contact_person.toLowerCase().includes(query)) ||
        (company.taxid && company.taxid.toLowerCase().includes(query))
    );
});

const openCreateModal = () => {
    form.clearErrors();
    form.reset();
    Object.assign(form, defaultFormData);
    showFormModal.value = true;
};

const openEditModal = (company) => {
    form.clearErrors();
    form.reset();
    form.id = company.id;
    form.name = company.name || '';
    form.abeta_id = company.abeta_id || '';
    form.status = company.status || 'Active';
    form.address = company.address || '';
    form.phone = company.phone || '';
    form.contact_person = company.contact_person || '';
    form.taxid = company.taxid || '';
    form.taxaddress = company.taxaddress || '';
    form.punchout_enabled = !!company.punchout_enabled;
    form.punchout_gateway = company.punchout_gateway || 'abeta';
    form.punchout_url = company.punchout_url || '';
    form.punchout_identity = company.punchout_identity || '';
    form.punchout_shared_secret = company.punchout_shared_secret || '';
    form.bargaining_enabled = company.hasOwnProperty('bargaining_enabled') ? !!company.bargaining_enabled : true;
    showFormModal.value = true;
};

const viewDetails = (company) => {
    selectedCompany.value = company;
    showDetailsModal.value = true;
};

const submitForm = () => {
    if (form.id) {
        form.put(route('admin.companies.update', form.id), {
            onSuccess: () => {
                showFormModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('admin.companies.store'), {
            onSuccess: () => {
                showFormModal.value = false;
                form.reset();
            }
        });
    }
};

const confirmDelete = (company) => {
    companyToDelete.value = company;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (companyToDelete.value) {
        useForm({}).delete(route('admin.companies.destroy', companyToDelete.value.id), {
            onFinish: () => {
                showDeleteModal.value = false;
                companyToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <Head title="Companies" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Company Registry</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage and view registered organizations, contact profiles, and fiscal metadata.</p>
                </div>
                <button 
                    v-if="isAdmin"
                    @click="openCreateModal"
                    class="px-4 py-2 bg-[#e96a25] hover:bg-[#cf5517] text-white rounded-xl font-bold text-sm shadow-sm transition flex items-center space-x-2 animate-fade-in"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Company</span>
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="relative w-full sm:max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search company name, contact, or Tax ID..."
                        class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-[#e96a25] focus:ring-1 focus:ring-[#e96a25] transition-colors placeholder:text-gray-400"
                    />
                </div>
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    Displaying {{ filteredCompanies.length }} registered organizations
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Company Info</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Contact Profile</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Tax / Registration</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Status</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 bg-white">
                            <tr v-for="company in filteredCompanies" :key="company.id" class="hover:bg-gray-50/70 transition-colors group">
                                <!-- Company Info -->
                                <td class="px-6 py-5">
                                    <div class="flex items-center space-x-3 cursor-pointer" @click="viewDetails(company)">
                                        <div class="w-10 h-10 flex-shrink-0 rounded-xl bg-gradient-to-br from-[#1a2b4c] to-[#274075] text-white flex items-center justify-center font-bold uppercase text-sm group-hover:scale-105 transition-transform">
                                            {{ company.name.substring(0, 2) }}
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-[#1a2b4c] text-sm group-hover:text-[#e96a25] transition-colors">{{ company.name }}</h4>
                                            <p class="text-xs text-gray-500 mt-0.5 flex items-center space-x-1">
                                                <span>ID:</span>
                                                <span class="font-medium font-mono text-gray-700">{{ company.abeta_id || 'N/A' }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact Profile -->
                                <td class="px-6 py-5">
                                    <div v-if="company.contact_person || company.phone">
                                        <p class="text-sm font-semibold text-gray-900">{{ company.contact_person || '—' }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5 flex items-center">
                                            <svg class="w-3.5 h-3.5 text-gray-400 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.211.896l-1.241 1.241a13.079 13.079 0 005.7 5.7l1.241-1.241a1 1 0 01.896-.211l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            {{ company.phone || 'N/A' }}
                                        </p>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 italic">No contacts</span>
                                </td>

                                <!-- Tax Info -->
                                <td class="px-6 py-5">
                                    <div>
                                        <p class="text-xs font-medium text-gray-500">Tax ID:</p>
                                        <p class="text-sm font-mono font-bold text-gray-800">{{ company.taxid || '—' }}</p>
                                        <p v-if="company.taxaddress" class="text-xs text-gray-400 truncate max-w-[200px] mt-0.5" :title="company.taxaddress">
                                            {{ company.taxaddress }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-5">
                                    <span :class="[
                                        company.status === 'Active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-200',
                                        'text-[11px] font-bold px-2 py-0.5 rounded-full tracking-wider uppercase'
                                    ]">
                                        {{ company.status }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button 
                                            @click="viewDetails(company)"
                                            class="p-1.5 text-gray-400 hover:text-[#1a2b4c] transition"
                                            title="View Details"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        
                                        <template v-if="isAdmin">
                                            <button 
                                                @click="openEditModal(company)"
                                                class="p-1.5 text-gray-400 hover:text-[#1a2b4c] transition"
                                                title="Edit Company"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button 
                                                @click="confirmDelete(company)"
                                                class="p-1.5 text-gray-400 hover:text-red-500 transition"
                                                title="Delete Company"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredCompanies.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <p class="text-sm font-medium">No companies found</p>
                                    <p class="text-xs text-gray-400 mt-1">Try modifying your search text.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Form Modal (Create / Edit) -->
        <Modal :show="showFormModal" @close="showFormModal = false" maxWidth="2xl">
            <form @submit.prevent="submitForm" class="p-6">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
                    <h3 class="text-lg font-bold text-[#1a2b4c]">
                        {{ form.id ? 'Modify Company Profile' : 'Register New Company' }}
                    </h3>
                    <button type="button" @click="showFormModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5">
                    <!-- Row 1: Name & Status -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <InputLabel for="name" value="Company Name *" />
                            <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required placeholder="Acme Corp" />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="status" value="Status *" />
                            <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.status" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="abeta_id" value="External Identifier (Abeta ID)" />
                        <TextInput id="abeta_id" type="text" class="mt-1 block w-full" v-model="form.abeta_id" placeholder="AB-12345" />
                        <InputError class="mt-2" :message="form.errors.abeta_id" />
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-6"></div>

                    <!-- SECTION: Contact details -->
                    <h4 class="text-sm font-bold text-gray-800">Primary Contact Details</h4>


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <InputLabel for="contact_person" value="Contact Person" />
                            <TextInput id="contact_person" type="text" class="mt-1 block w-full" v-model="form.contact_person" placeholder="John Doe" />
                            <InputError class="mt-2" :message="form.errors.contact_person" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Contact Phone" />
                            <TextInput id="phone" type="text" class="mt-1 block w-full" v-model="form.phone" placeholder="+1 (555) 123-4567" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="address" value="Operational Address" />
                        <textarea id="address" v-model="form.address" rows="2" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25] text-sm" placeholder="123 Business St, City"></textarea>
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-6"></div>

                    <!-- SECTION: Tax and fiscal details -->
                    <h4 class="text-sm font-bold text-gray-800">Fiscal & Registration Details</h4>

                    <div>
                        <InputLabel for="taxid" value="Tax Identification Number (Tax ID)" />
                        <TextInput id="taxid" type="text" class="mt-1 block w-full" v-model="form.taxid" placeholder="TAX-987654321" />
                        <InputError class="mt-2" :message="form.errors.taxid" />
                    </div>

                    <div>
                        <InputLabel for="taxaddress" value="Registered Tax Address" />
                        <textarea id="taxaddress" v-model="form.taxaddress" rows="2" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25] text-sm" placeholder="Official registered tax address (if different from operational)"></textarea>
                        <InputError class="mt-2" :message="form.errors.taxaddress" />
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-6"></div>

                    <!-- SECTION: Bargaining Integration -->
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold text-gray-800">Bargaining Option</h4>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.bargaining_enabled" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#e96a25]"></div>
                            <span class="ms-3 text-xs font-medium text-gray-700">Enable Bargaining</span>
                        </label>
                    </div>

                    <div class="border-t border-dashed border-gray-200 my-6"></div>

                    <!-- SECTION: PunchOut Integration -->
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold text-gray-800">PunchOut Configuration</h4>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.punchout_enabled" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#e96a25]"></div>
                            <span class="ms-3 text-xs font-medium text-gray-700">Enable PunchOut</span>
                        </label>
                    </div>

                    <transition 
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div v-if="form.punchout_enabled" class="mt-4 space-y-4 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <InputLabel for="punchout_gateway" value="Gateway Provider" />
                                    <select id="punchout_gateway" v-model="form.punchout_gateway" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25] text-sm">
                                        <option value="abeta">Abeta Integration</option>
                                        <option value="oci">SAP OCI (Open Catalog Interface)</option>
                                        <option value="cxml">cXML Standard Protocol</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.punchout_gateway" />
                                </div>
                                <div>
                                    <InputLabel for="punchout_identity" value="Identity / Credential" />
                                    <TextInput id="punchout_identity" type="text" class="mt-1 block w-full" v-model="form.punchout_identity" placeholder="Company Identity Key" />
                                    <InputError class="mt-2" :message="form.errors.punchout_identity" />
                                </div>
                            </div>

                            <div>
                                <InputLabel for="punchout_url" value="PunchOut Setup URL" />
                                <TextInput id="punchout_url" type="text" class="mt-1 block w-full" v-model="form.punchout_url" placeholder="https://example.com/punchout/setup" />
                                <InputError class="mt-2" :message="form.errors.punchout_url" />
                            </div>

                            <div>
                                <InputLabel for="punchout_shared_secret" value="Shared Secret" />
                                <TextInput id="punchout_shared_secret" type="password" class="mt-1 block w-full" v-model="form.punchout_shared_secret" placeholder="••••••••" />
                                <InputError class="mt-2" :message="form.errors.punchout_shared_secret" />
                            </div>
                        </div>
                    </transition>
                </div>

                <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end space-x-3">
                    <SecondaryButton @click="showFormModal = false" :disabled="form.processing">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ form.id ? 'Save Changes' : 'Create Organization' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Detail Viewer Modal -->
        <Modal :show="showDetailsModal" @close="showDetailsModal = false" maxWidth="xl">
            <div class="p-6" v-if="selectedCompany">
                <div class="flex items-center space-x-4 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-[#1a2b4c] text-white flex items-center justify-center font-bold text-lg uppercase">
                        {{ selectedCompany.name.substring(0, 2) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-[#1a2b4c]">{{ selectedCompany.name }}</h3>
                        <span :class="[
                            selectedCompany.status === 'Active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-gray-100 text-gray-600 border-gray-200',
                            'text-[10px] font-bold px-2 py-0.5 border rounded-md tracking-wider uppercase mt-1 inline-block'
                        ]">
                            {{ selectedCompany.status }}
                        </span>
                    </div>
                </div>

                <div class="space-y-5 bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <div>
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Identification</h4>
                        <p class="text-sm text-gray-800 font-medium font-mono">{{ selectedCompany.abeta_id || 'Not registered' }}</p>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-3">
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Contact Info</h4>
                        <p class="text-sm font-semibold text-gray-900">{{ selectedCompany.contact_person || 'Not provided' }}</p>
                        <p class="text-sm text-gray-700 mt-0.5" v-if="selectedCompany.phone">{{ selectedCompany.phone }}</p>
                    </div>

                    <div class="border-t border-gray-200 pt-3">
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Operational Address</h4>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ selectedCompany.address || 'No registered address' }}</p>
                    </div>

                    <div v-if="selectedCompany.punchout_enabled" class="border-t border-gray-200 pt-3">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                            <h4 class="text-xs font-bold text-indigo-600 uppercase tracking-wider">PunchOut Config</h4>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase block">Gateway</span>
                                <span class="text-sm text-gray-800 font-semibold uppercase">{{ selectedCompany.punchout_gateway }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase block">Identity</span>
                                <span class="text-sm text-gray-800 font-mono">{{ selectedCompany.punchout_identity || 'N/A' }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">Setup URL</span>
                            <span class="text-xs text-gray-600 break-all select-all">{{ selectedCompany.punchout_url || 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-3 bg-orange-50/30 -mx-4 -mb-4 p-4 rounded-b-xl border-t border-orange-100/50">
                        <h4 class="text-xs font-bold text-orange-600 uppercase tracking-wider mb-1">Fiscal Details</h4>
                        <div class="flex flex-col space-y-2">
                            <div>
                                <span class="text-[11px] font-bold text-gray-400 block">Tax ID:</span>
                                <span class="text-sm font-mono font-bold text-gray-800">{{ selectedCompany.taxid || 'Not registered' }}</span>
                            </div>
                            <div>
                                <span class="text-[11px] font-bold text-gray-400 block">Tax Address:</span>
                                <span class="text-sm text-gray-700 whitespace-pre-line">{{ selectedCompany.taxaddress || 'Same as Operational' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDetailsModal = false">Close</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal :show="showDeleteModal" @close="showDeleteModal = false">
            <template #title>
                Delete Company
            </template>
            <template #content>
                Are you sure you want to delete <span class="font-bold text-[#1a2b4c]">{{ companyToDelete?.name }}</span>? This action is permanent and cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                <PrimaryButton class="ml-3 bg-red-600 hover:bg-red-700 focus:ring-red-500" @click="executeDelete">
                    Delete Company
                </PrimaryButton>
            </template>
        </ConfirmationModal>
    </AuthenticatedLayout>
</template>
