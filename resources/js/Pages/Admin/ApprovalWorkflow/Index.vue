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
    workflows: Array,
    companies: Array,
    approverUsers: Array,
});

const page = usePage();
const showCreateModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const searchQuery = ref('');

const filteredWorkflows = computed(() => {
    if (!searchQuery.value) return props.workflows;
    const q = searchQuery.value.toLowerCase();
    return props.workflows.filter(w => {
        const companyName = w.company ? w.company.name.toLowerCase() : 'all companies default';
        const ruleName = w.name.toLowerCase();
        return companyName.includes(q) || ruleName.includes(q);
    });
});

const form = useForm({
    name: '',
    company_id: '',
    min_amount: 0,
    require_sequential: true,
    steps: [
        { name: 'Level 1 Approval', description: '', approver_ids: [] }
    ]
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.clearErrors();
    form.reset();
    form.steps = [{ name: 'Level 1 Approval', description: '', approver_ids: [] }];
    showCreateModal.value = true;
};

const openEditModal = (workflow) => {
    isEditing.value = true;
    editingId.value = workflow.id;
    form.clearErrors();
    form.reset();
    
    form.name = workflow.name;
    form.company_id = workflow.company_id || '';
    form.min_amount = workflow.min_amount;
    form.require_sequential = !!workflow.require_sequential;
    
    form.steps = workflow.steps.map(s => ({
        name: s.name,
        description: s.description || '',
        approver_ids: s.approvers.map(a => a.id)
    }));
    
    showCreateModal.value = true;
};

const addStep = () => {
    form.steps.push({
        name: `Level ${form.steps.length + 1} Approval`,
        description: '',
        approver_ids: []
    });
};

const removeStep = (index) => {
    if (form.steps.length > 1) {
        form.steps.splice(index, 1);
    }
};

const toggleApproverInStep = (stepIndex, userId) => {
    const currentStep = form.steps[stepIndex];
    const index = currentStep.approver_ids.indexOf(userId);
    if (index === -1) {
        currentStep.approver_ids.push(userId);
    } else {
        currentStep.approver_ids.splice(index, 1);
    }
};

const isApproverSelected = (stepIndex, userId) => {
    return form.steps[stepIndex].approver_ids.includes(userId);
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('admin.workflows.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showCreateModal.value = false;
            }
        });
    } else {
        form.post(route('admin.workflows.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showCreateModal.value = false;
            }
        });
    }
};

const workflowToDelete = ref(null);
const showDeleteModal = ref(false);

const deleteWorkflow = (id) => {
    workflowToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDeleteWorkflow = () => {
    if (workflowToDelete.value) {
        useForm({}).delete(route('admin.workflows.destroy', workflowToDelete.value), {
            onFinish: () => {
                showDeleteModal.value = false;
                workflowToDelete.value = null;
            }
        });
    }
};

const formattedMinAmount = computed({
    get() {
        if (form.min_amount === '' || form.min_amount === null || form.min_amount === undefined) return '';
        const parts = form.min_amount.toString().split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return parts.join('.');
    },
    set(val) {
        const cleaned = val.replace(/,/g, '');
        if (cleaned === '' || !isNaN(cleaned)) {
            form.min_amount = cleaned;
        }
    }
});

const formatCurrency = (value) => {
    const currency = page.props.appSettings?.currency || 'USD';
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    } catch (e) {
        return `${currency} ${Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }
};
</script>

<template>
    <Head title="Approval Workflows" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Approval Workflow Settings</h2>
                    <p class="text-sm text-gray-500 mt-1">Define sequential approval groups based on order amounts and companies.</p>
                </div>
                <button 
                    @click="openCreateModal"
                    class="px-4 py-2 bg-[#1a2b4c] hover:bg-[#2a3b5c] text-white rounded-xl font-bold text-sm shadow-sm transition flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Create Rule</span>
                </button>
            </div>
        </template>

        <div class="py-6">
            <!-- Interactive Search Architecture -->
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4" v-if="workflows.length > 0">
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1a2b4c] focus:border-transparent sm:text-sm transition shadow-sm" 
                        placeholder="Search by company or rule name..."
                    />
                    <div v-if="searchQuery" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div v-if="searchQuery" class="text-sm text-gray-500">
                    Found <span class="font-bold text-[#1a2b4c]">{{ filteredWorkflows.length }}</span> results.
                </div>
            </div>

            <div v-if="workflows.length === 0" class="text-center py-12 bg-white border border-gray-100 rounded-2xl shadow-sm">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-[#1a2b4c]">No Workflow Rules Defined</h3>
                <p class="text-sm text-gray-500 mt-1 max-w-xs mx-auto">Set up financial thresholds and hierarchical approvals for purchase orders.</p>
                <PrimaryButton @click="openCreateModal" class="mt-4 bg-[#e96a25]">Build Your First Rule</PrimaryButton>
            </div>

            <!-- Zero State for Filtered Search -->
            <div v-else-if="filteredWorkflows.length === 0" class="text-center py-12 bg-white border border-gray-100 rounded-2xl shadow-sm">
                 <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-[#1a2b4c]">No Matching Results</h3>
                <p class="text-sm text-gray-500 mt-1">We couldn't find any rules matching "{{ searchQuery }}".</p>
                <button @click="searchQuery = ''" class="mt-4 text-sm font-bold text-[#e96a25] hover:underline">Clear Filter</button>
            </div>

            <div class="space-y-6" v-else>
                <div v-for="workflow in filteredWorkflows" :key="workflow.id" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-start justify-between bg-gray-50/50">
                        <div>
                            <div class="flex items-center space-x-3 mb-1">
                                <h3 class="text-lg font-bold text-[#1a2b4c]">{{ workflow.name }}</h3>
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-700 border border-blue-200" v-if="workflow.require_sequential">Sequential</span>
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-600 border border-gray-200" v-else>Parallel Groups</span>
                            </div>
                            <div class="flex flex-wrap gap-y-2 items-center text-sm text-gray-500 space-x-4">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5" /></svg>
                                    {{ workflow.company ? workflow.company.name : 'All Companies (Default)' }}
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Max Order: <b class="ml-1 text-gray-900">{{ formatCurrency(workflow.min_amount) }}</b>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button 
                                @click="openEditModal(workflow)" 
                                class="p-1.5 text-gray-400 hover:text-[#e96a25] hover:bg-orange-50 rounded-lg transition-all"
                                title="Edit Rules"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button 
                                @click="deleteWorkflow(workflow.id)" 
                                class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                title="Delete Workflow"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Steps Visualization -->
                    <div class="p-6 relative">
                        <div class="flex items-stretch space-x-8 overflow-x-auto pt-4 pb-4 px-1">
                            <div v-for="(step, idx) in workflow.steps" :key="step.id" class="flex-shrink-0 w-64 relative group">
                                <!-- Arrow Connector -->
                                <div v-if="idx < workflow.steps.length - 1" class="absolute top-6 -right-6 text-gray-300 z-10">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                </div>

                                <div class="h-full bg-white border border-gray-200 rounded-xl p-4 relative hover:border-[#e96a25] transition shadow-sm">
                                    <div class="absolute top-0 left-4 transform -translate-y-1/2 bg-[#1a2b4c] text-white text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded">
                                        Step {{ idx + 1 }}
                                    </div>
                                    <h4 class="font-bold text-gray-900 text-sm mt-1 truncate">{{ step.name }}</h4>
                                    <p class="text-xs text-gray-500 line-clamp-2 min-h-[2.5em] mb-3">{{ step.description || 'No instruction details.' }}</p>
                                    
                                    <div class="pt-3 border-t border-gray-50">
                                        <p class="text-[10px] font-bold uppercase text-gray-400 tracking-wider mb-2">Allowed Approvers</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <div v-for="approver in step.approvers" :key="approver.id" class="inline-flex items-center bg-gray-50 px-2 py-1 rounded-md border border-gray-100 text-xs font-medium text-gray-700">
                                                <div class="w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-[9px] font-bold mr-1.5">{{ approver.name.substring(0,1) }}</div>
                                                {{ approver.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Massive Create Workflow Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6 border-b pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-[#1a2b4c]">{{ isEditing ? 'Update Approval Pipeline' : 'Construct Approval Pipeline' }}</h3>
                        <p class="text-sm text-gray-500">{{ isEditing ? 'Modify validation logic and hierarchies.' : 'Design hierarchical triggers for purchase control.' }}</p>
                    </div>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-6 max-h-[70vh] overflow-y-auto px-4 pb-4">
                    <!-- Root Configuration -->
                    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <div class="col-span-2">
                            <InputLabel for="ruleName" value="Rule Descriptor Name" />
                            <TextInput id="ruleName" v-model="form.name" class="w-full mt-1" placeholder="High Value Procurement Approval" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="Buyer Scope" />
                            <select v-model="form.company_id" class="w-full mt-1 rounded-lg border-gray-300 shadow-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 text-sm">
                                <option value="">Apply to All Companies (General Fallback)</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Maximum Order Amount Limit" />
                            <TextInput type="text" v-model="formattedMinAmount" class="w-full mt-1" placeholder="0.00" required />
                        </div>
                        <div class="col-span-2 flex items-center space-x-3 pt-2 border-t border-gray-200/50 mt-2">
                            <input type="checkbox" id="isSeq" v-model="form.require_sequential" class="rounded text-[#e96a25] focus:ring-[#e96a25]" />
                            <label for="isSeq" class="text-sm font-bold text-gray-700">Require Strict Sequential Steps (Step 1 -> Step 2 -> Step 3)</label>
                        </div>
                    </div>

                    <!-- Sequential Step Builder -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-bold text-[#1a2b4c]">Workflow Stages Definition</h4>
                            <button type="button" @click="addStep" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center">+ Add Execution Tier</button>
                        </div>

                        <div class="space-y-4">
                            <div v-for="(step, idx) in form.steps" :key="idx" class="relative border border-gray-200 rounded-xl p-4 bg-white shadow-sm">
                                <!-- Floating Rank Badge -->
                                <div class="absolute -left-2 top-4 bg-gray-900 text-white text-xs font-black w-6 h-6 rounded-full flex items-center justify-center ring-4 ring-white">
                                    {{ idx + 1 }}
                                </div>

                                <div class="ml-6">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1 grid grid-cols-2 gap-4 mr-4">
                                            <div>
                                                <InputLabel :value="'Step Name'" class="text-[11px] text-gray-500 uppercase font-bold" />
                                                <TextInput v-model="step.name" class="w-full text-sm py-1.5" placeholder="e.g. Dept Manager Review" required />
                                            </div>
                                            <div>
                                                <InputLabel value="Instructional Note (Optional)" class="text-[11px] text-gray-500 uppercase font-bold" />
                                                <TextInput v-model="step.description" class="w-full text-sm py-1.5" placeholder="Verify invoice alignment" />
                                            </div>
                                        </div>
                                        <button type="button" v-if="form.steps.length > 1" @click="removeStep(idx)" class="text-gray-300 hover:text-red-500 mt-5">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        </button>
                                    </div>

                                    <div class="mt-3 pt-3 border-t border-gray-50">
                                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-gray-400 mb-2">Select Qualified Approver Pool (Multiple Allowed)</label>
                                        
                                        <div v-if="approverUsers.length === 0" class="text-xs text-amber-600 italic bg-amber-50 p-2 rounded">
                                            Warning: No users exist with "supplier_approver" role. Configure roles first.
                                        </div>
                                        
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <div 
                                                v-for="u in approverUsers" :key="u.id"
                                                @click="toggleApproverInStep(idx, u.id)"
                                                :class="[
                                                    'cursor-pointer border rounded-lg p-2 flex items-center space-x-2 transition select-none text-xs',
                                                    isApproverSelected(idx, u.id) 
                                                        ? 'border-[#e96a25] bg-orange-50/50 text-[#e96a25]' 
                                                        : 'border-gray-100 bg-gray-50 hover:bg-gray-100 text-gray-600'
                                                ]"
                                            >
                                                <div :class="[
                                                    'w-3 h-3 border rounded flex items-center justify-center',
                                                    isApproverSelected(idx, u.id) ? 'bg-[#e96a25] border-[#e96a25]' : 'border-gray-300 bg-white'
                                                ]">
                                                    <svg v-if="isApproverSelected(idx, u.id)" class="w-2 h-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"/></svg>
                                                </div>
                                                <span class="truncate font-medium">{{ u.name }}</span>
                                            </div>
                                        </div>
                                        <InputError :message="form.errors[`steps.${idx}.approver_ids`]" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-white border-t pt-4 mt-8 flex justify-end space-x-3 pb-2">
                        <SecondaryButton @click="showCreateModal = false" type="button">Abort</SecondaryButton>
                        <PrimaryButton 
                            class="bg-[#1a2b4c] hover:bg-[#2a3b5c]"
                            :class="{ 'opacity-25': form.processing }" 
                            :disabled="form.processing"
                        >
                            {{ isEditing ? 'Save Changes' : 'Deploy Workflow' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Workflow Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Delete Approval Workflow"
            message="Are you sure you want to permanently delete this workflow rule? Existing pending orders attached to it will revert to fallback rules."
            type="danger"
            confirmLabel="Delete Rule"
            @close="showDeleteModal = false; workflowToDelete = null"
            @confirm="executeDeleteWorkflow"
        />
    </AuthenticatedLayout>
</template>
