<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    orders: Object,
    companies: Array,
    filters: Object,
    statuses: Array,
});

const page = usePage();
const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val); }
    catch (e) { return `${currency} ${Number(val).toFixed(2)}`; }
};

// Filters state
const filterMonth = ref(props.filters.month || '');
const filterStatus = ref(props.filters.status || '');
const filterCompany = ref(props.filters.company_id || '');

// Handle quick filter updates
watch([filterMonth, filterStatus, filterCompany], () => {
    router.get(route('admin.orders.index'), {
        month: filterMonth.value,
        status: filterStatus.value,
        company_id: filterCompany.value,
    }, {
        preserveState: true,
        replace: true,
    });
});

const resetFilters = () => {
    filterMonth.value = '';
    filterStatus.value = '';
    filterCompany.value = '';
};

// View Modal state
const showModal = ref(false);
const selectedOrder = ref(null);
const loadingDetails = ref(false);

const openDetails = async (orderId) => {
    showModal.value = true;
    loadingDetails.value = true;
    try {
        const res = await axios.get(route('admin.orders.show', orderId));
        selectedOrder.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingDetails.value = false;
    }
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => { selectedOrder.value = null; }, 300);
};

// Update Status form helper
const statusForm = useForm({
    status: '',
    rejection_reason: '',
    note: '',
});

const submitStatusUpdate = (orderId) => {
    statusForm.patch(route('admin.orders.update-status', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            // Complete Reload to ensure fresh workflow/can_approve states are fetched!
            openDetails(orderId);
            statusForm.reset('status', 'rejection_reason', 'note');
        },
    });
};

const prepareStatusUpdate = (st) => {
    statusForm.status = st;
    statusForm.rejection_reason = '';
    statusForm.note = '';
};

import ConfirmationModal from '@/Components/ConfirmationModal.vue';

// Batch Update Functionality
const selectedOrderIds = ref([]);
const showBatchConfirmModal = ref(false);

const isAllSelected = computed(() => {
    if (!props.orders?.data || props.orders.data.length === 0) return false;
    return props.orders.data.every(order => selectedOrderIds.value.includes(order.id));
});

const toggleAll = () => {
    if (isAllSelected.value) {
        selectedOrderIds.value = [];
    } else {
        selectedOrderIds.value = props.orders.data.map(o => o.id);
    }
};

const batchForm = useForm({
    order_ids: [],
    status: '',
    rejection_reason: ''
});

const alertModal = ref({
    show: false,
    title: '',
    message: ''
});

const initiateBatchStatusUpdate = () => {
    if (!batchForm.status) return;
    if (batchForm.status === 'Rejected' && !batchForm.rejection_reason) {
        alertModal.value = {
            show: true,
            title: 'Rejection Reason Required',
            message: 'Please provide a rejection reason to batch reject the selected orders.'
        };
        return;
    }
    showBatchConfirmModal.value = true;
};

const executeBatchStatusUpdate = () => {
    showBatchConfirmModal.value = false;
    batchForm.order_ids = [...selectedOrderIds.value];
    batchForm.post(route('admin.orders.batch-update-status'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedOrderIds.value = [];
            batchForm.reset('status', 'rejection_reason', 'order_ids');
        }
    });
};

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'RFQ': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Submitted': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Approved': return 'bg-teal-50 text-teal-700 border-teal-200';
        case 'Quotation': return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        case 'PO': return 'bg-violet-50 text-violet-700 border-violet-200';
        case 'Invoiced': return 'bg-cyan-50 text-cyan-700 border-cyan-200';
        case 'Shipped': return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'Completed': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Rejected': return 'bg-red-50 text-red-700 border-red-200';
        default: return 'bg-gray-50 text-gray-700 border-gray-200';
    }
};

</script>

<template>
    <Head title="Global Orders History" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Order Management</h2>
                    <p class="text-sm text-gray-500 mt-1">Monitor and manage orders placed across all connected companies.</p>
                </div>
            </div>
        </template>

        <div class="py-8 space-y-6">
            <!-- Filter Controls -->
            <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-6">
                <div class="flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-4">
                    
                    <!-- Month Filter -->
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">By Month</label>
                        <input type="month" v-model="filterMonth" 
                            class="w-full border-gray-200 rounded-xl text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm transition" />
                    </div>

                    <!-- Status Filter -->
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">By Status</label>
                        <select v-model="filterStatus" 
                            class="w-full border-gray-200 rounded-xl text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm transition">
                            <option value="">All Statuses</option>
                            <option v-for="st in props.statuses" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>

                    <!-- Company Filter -->
                    <div class="flex-1 min-w-[250px]">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">By Company</label>
                        <select v-model="filterCompany" 
                            class="w-full border-gray-200 rounded-xl text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm transition">
                            <option value="">All Companies</option>
                            <option v-for="comp in props.companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
                        </select>
                    </div>

                    <div class="flex items-end pt-5">
                        <button @click="resetFilters" 
                            class="text-sm text-gray-500 hover:text-[#e96a25] font-semibold py-2 px-4 transition-colors">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table view -->
            <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
                <!-- Batch Actions Bar -->
                <div v-if="selectedOrderIds.length > 0" class="bg-[#1a2b4c]/5 px-6 py-4 border-b border-[#1a2b4c]/10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[#1a2b4c] text-white font-black text-sm shadow-sm">
                            {{ selectedOrderIds.length }}
                        </div>
                        <p class="text-sm font-bold text-[#1a2b4c]">Orders selected for update</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        <div v-if="batchForm.status === 'Rejected'" class="min-w-[220px]">
                            <input type="text" v-model="batchForm.rejection_reason" placeholder="Uniform reason for rejection..." 
                                class="w-full border-gray-200 rounded-xl text-sm py-1.5 focus:border-red-500 focus:ring-red-500/20 shadow-sm" />
                        </div>
                        
                        <select v-model="batchForm.status" 
                            class="border-gray-200 rounded-xl text-sm font-bold text-[#1a2b4c] py-1.5 focus:border-[#e96a25] focus:ring-[#e96a25]/20 shadow-sm bg-white min-w-[160px]">
                            <option value="" disabled selected>-- Choose status --</option>
                            <option v-for="st in props.statuses" :key="`batch-${st}`" :value="st">Mark as {{ st }}</option>
                        </select>
                        
                        <button @click="initiateBatchStatusUpdate" :disabled="!batchForm.status || batchForm.processing"
                            class="bg-[#e96a25] hover:bg-[#d1591b] text-white px-4 py-1.5 rounded-xl text-sm font-bold shadow-sm disabled:opacity-50 transition flex items-center gap-2">
                            <svg v-if="batchForm.processing" class="animate-spin h-3 w-3 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Apply Update
                        </button>
                        
                        <button @click="selectedOrderIds = []" class="text-xs text-gray-500 hover:text-[#1a2b4c] font-bold uppercase tracking-wider transition-colors px-2">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-4 w-10 text-center">
                                    <input type="checkbox" 
                                        :checked="isAllSelected" 
                                        @change="toggleAll"
                                        class="rounded border-gray-300 text-[#e96a25] focus:ring-[#e96a25]/20 shadow-sm" />
                                </th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Order ID / Date</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Company</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Purchaser</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-center">Items</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Total</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Status</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="order in props.orders.data" :key="order.id" 
                                :class="['transition duration-150', selectedOrderIds.includes(order.id) ? 'bg-[#e96a25]/5 hover:bg-[#e96a25]/10' : 'hover:bg-gray-50/80']">
                                <td class="px-6 py-5 text-center">
                                    <input type="checkbox" 
                                        :value="order.id" 
                                        v-model="selectedOrderIds"
                                        class="rounded border-gray-300 text-[#e96a25] focus:ring-[#e96a25]/20 shadow-sm" />
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-black text-[#1a2b4c]">#{{ String(order.id).padStart(6, '0') }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5 font-medium">
                                        {{ new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="font-bold text-gray-800 text-sm">{{ order.company?.name || 'Unknown' }}</span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="text-sm text-gray-700 font-semibold">{{ order.user?.name || 'System' }}</div>
                                    <div class="text-xs text-gray-400 mt-0.5">{{ order.user?.email }}</div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-extrabold">{{ order.items_count }}</span>
                                </td>
                                <td class="px-6 py-5 font-bold text-[#1a2b4c] text-sm">
                                    {{ formatCurrency(order.total) }}
                                </td>
                                <td class="px-6 py-5">
                                    <span :class="['px-3 py-1 border rounded-full text-xs font-bold', getStatusBadgeClass(order.status)]">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <button @click="openDetails(order.id)" 
                                        class="bg-white border border-gray-200 text-[#1a2b4c] hover:border-[#e96a25] hover:text-[#e96a25] px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition duration-200">
                                        View / Update
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="props.orders.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-400 font-medium italic">
                                    No orders found matching criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination area -->
                <div class="px-6 py-4 border-t border-gray-50">
                    <Pagination :links="props.orders.links" />
                </div>
            </div>
        </div>

        <!-- Details & Status Management Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay -->
                <div @click="closeModal" class="fixed inset-0 bg-[#1a2b4c]/40 backdrop-blur-sm transition-opacity"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full border border-gray-100">
                    
                    <div v-if="loadingDetails" class="p-20 flex flex-col items-center justify-center space-y-4">
                        <div class="w-10 h-10 border-4 border-[#e96a25]/20 border-t-[#e96a25] rounded-full animate-spin"></div>
                        <span class="text-gray-500 font-semibold text-sm">Loading order details...</span>
                    </div>

                    <div v-else-if="selectedOrder">
                        <!-- Header -->
                        <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-black text-[#1a2b4c]">Order #{{ String(selectedOrder.id).padStart(6, '0') }}</h3>
                                <p class="text-xs text-gray-500 font-medium">Placed on {{ new Date(selectedOrder.created_at).toLocaleString() }}</p>
                            </div>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-6">
                            <!-- Summary -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                    <div class="text-xs font-bold text-gray-400 uppercase mb-1">Customer Entity</div>
                                    <div class="text-base font-black text-[#1a2b4c]">{{ selectedOrder.company?.name }}</div>
                                    <div class="text-sm text-gray-600 mt-1">{{ selectedOrder.user?.name }} ({{ selectedOrder.user?.email }})</div>
                                </div>
                                <div class="bg-[#e96a25]/5 p-4 rounded-xl border border-[#e96a25]/10 flex flex-col justify-center">
                                    <div class="text-xs font-bold text-[#e96a25] uppercase mb-1">Total Value</div>
                                    <div class="text-2xl font-black text-[#e96a25]">{{ formatCurrency(selectedOrder.total) }}</div>
                                </div>
                            </div>

                            <!-- cXML Integration & B2B Details -->
                            <div v-if="selectedOrder.punchout_po_reference" class="bg-[#1a2b4c]/5 border border-[#1a2b4c]/10 rounded-xl p-4 space-y-3">
                                <div class="flex items-center justify-between border-b border-[#1a2b4c]/10 pb-2">
                                    <h4 class="font-extrabold text-xs text-[#1a2b4c] uppercase tracking-wider flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        Inbound ERP PO Payload Reference
                                    </h4>
                                    <span v-if="selectedOrder.deployment_mode" :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase border', selectedOrder.deployment_mode === 'test' ? 'bg-amber-100 border-amber-200 text-amber-800' : 'bg-green-100 border-green-200 text-green-800']">
                                        Mode: {{ selectedOrder.deployment_mode }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <span class="text-xs font-bold text-gray-400 block uppercase tracking-wider">Purchase Order Ref</span>
                                        <span class="font-black font-mono text-[#1a2b4c] text-sm">{{ selectedOrder.punchout_po_reference }}</span>
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-gray-400 block uppercase tracking-wider">ERP Transmittal Date</span>
                                        <span class="font-semibold text-gray-700">{{ selectedOrder.po_date || '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-gray-400 block uppercase tracking-wider">Settlement Currency</span>
                                        <span class="font-black text-gray-800">{{ selectedOrder.currency || 'USD' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Address Blocks -->
                            <div v-if="selectedOrder.shipping_address || selectedOrder.billing_address" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Shipping Address -->
                                <div v-if="selectedOrder.shipping_address" class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                    <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2.5 flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        Delivery Details
                                    </h4>
                                    <div class="text-sm text-gray-800 font-black" v-if="selectedOrder.shipping_name">{{ selectedOrder.shipping_name }}</div>
                                    <div class="text-xs text-gray-500 font-medium mb-1.5" v-if="selectedOrder.shipping_email">{{ selectedOrder.shipping_email }}</div>
                                    <div class="text-xs text-gray-600 whitespace-pre-line leading-relaxed font-bold bg-white border border-gray-100 p-2 rounded-lg">{{ selectedOrder.shipping_address }}</div>
                                </div>
                                <!-- Billing Address -->
                                <div v-if="selectedOrder.billing_address" class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                    <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2.5 flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Invoicing Address
                                    </h4>
                                    <div class="text-sm text-gray-800 font-black" v-if="selectedOrder.billing_name">{{ selectedOrder.billing_name }}</div>
                                    <div class="text-xs text-gray-500 font-medium mb-1.5" v-if="selectedOrder.billing_email">{{ selectedOrder.billing_email }}</div>
                                    <div class="text-xs text-gray-600 whitespace-pre-line leading-relaxed font-bold bg-white border border-gray-100 p-2 rounded-lg">{{ selectedOrder.billing_address }}</div>
                                </div>
                            </div>

                            <!-- Rejection Reason if present -->
                            <div v-if="selectedOrder.status === 'Rejected' && selectedOrder.rejection_reason" class="bg-red-50/50 border border-red-100 rounded-xl p-4 text-sm text-red-800">
                                <div class="font-bold text-xs uppercase tracking-wider text-red-600 mb-1">Rejection Reason</div>
                                "{{ selectedOrder.rejection_reason }}"
                            </div>

                            <!-- Items table -->
                            <div>
                                <h4 class="font-bold text-sm text-gray-700 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                    Ordered Items
                                </h4>
                                <div class="border border-gray-100 rounded-xl overflow-hidden">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase">
                                            <tr>
                                                <th class="px-4 py-3">Product</th>
                                                <th class="px-4 py-3 text-right">Qty</th>
                                                <th class="px-4 py-3 text-right">Price</th>
                                                <th class="px-4 py-3 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr v-for="item in selectedOrder.items" :key="item.id">
                                                <td class="px-4 py-3">
                                                    <template v-if="item.product">
                                                        <Link :href="route('catalog.show', item.product.id)" class="font-bold text-[#1a2b4c] hover:text-[#e96a25] hover:underline transition">
                                                            {{ item.product.name }}
                                                        </Link>
                                                        <div class="text-xs text-gray-400 mt-0.5 font-medium flex flex-wrap gap-x-2 gap-y-0.5 items-center">
                                                            <span class="font-mono">SKU: {{ item.product.sku }}</span>
                                                            <span v-if="item.product.uom" class="text-[#e96a25]">• UOM: {{ item.product.uom }}</span>
                                                            <span v-if="item.product.classification" class="text-indigo-600 italic">• UN: {{ item.product.classification }}</span>
                                                        </div>
                                                        <div v-if="item.product.manufacturer_part_id || item.product.manufacturer_name" class="text-[10px] text-gray-400 uppercase tracking-wider mt-0.5">
                                                            MFG: {{ item.product.manufacturer_name || '-' }} [{{ item.product.manufacturer_part_id || '-' }}]
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="font-bold text-gray-400">Deleted Product</div>
                                                    </template>
                                                </td>
                                                <td class="px-4 py-3 text-right font-medium text-gray-600">{{ item.quantity }}</td>
                                                <td class="px-4 py-3 text-right font-medium text-gray-600">{{ formatCurrency(item.price) }}</td>
                                                <td class="px-4 py-3 text-right font-bold text-[#1a2b4c]">
                                                    {{ formatCurrency(item.price * item.quantity) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Approval History / Logs -->
                            <div v-if="selectedOrder.approval_logs && selectedOrder.approval_logs.length > 0">
                                <h4 class="font-bold text-sm text-gray-700 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    Approval History & Activity
                                </h4>
                                <div class="bg-gray-50/50 rounded-xl border border-gray-100 p-4">
                                    <div class="flow-root">
                                        <ul role="list" class="-mb-8">
                                            <li v-for="(log, logIdx) in selectedOrder.approval_logs" :key="log.id">
                                                <div class="relative pb-8">
                                                    <span v-if="logIdx !== selectedOrder.approval_logs.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                    
                                                    <div class="relative flex space-x-3">
                                                        <div>
                                                            <span :class="[
                                                                'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                                                                log.action.toLowerCase().includes('approve') ? 'bg-emerald-100' : (log.action.toLowerCase().includes('reject') ? 'bg-red-100' : 'bg-blue-100')
                                                            ]">
                                                                <svg v-if="log.action.toLowerCase().includes('approve')" class="h-4 w-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                                <svg v-else-if="log.action.toLowerCase().includes('reject')" class="h-4 w-4 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                                <svg v-else class="h-4 w-4 text-blue-600" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>
                                                            </span>
                                                        </div>
                                                        <div class="min-w-0 flex-1 flex justify-between space-x-4">
                                                            <div>
                                                                <p class="text-xs text-gray-500">
                                                                    <span class="font-bold text-gray-900">{{ log.user?.name || 'System' }}</span> 
                                                                    <span class="mx-1 uppercase tracking-wide text-[10px] font-black" :class="log.action.toLowerCase().includes('approve') ? 'text-emerald-600' : (log.action.toLowerCase().includes('reject') ? 'text-red-600' : 'text-gray-600')">{{ log.action }}</span> 
                                                                    this order.
                                                                </p>
                                                                <p v-if="log.note" class="text-xs italic text-gray-500 mt-1 border-l-2 border-gray-200 pl-2">
                                                                    "{{ log.note }}"
                                                                </p>
                                                            </div>
                                                            <div class="text-right text-xs whitespace-nowrap text-gray-400 font-medium">
                                                                {{ new Date(log.created_at).toLocaleDateString() }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Status Action -->
                            <div class="pt-4 border-t border-gray-100">
                                <h4 class="font-bold text-sm text-gray-700 mb-3">Update Order Status</h4>
                                <div v-if="selectedOrder.status !== 'Completed' && selectedOrder.status !== 'Rejected'" class="flex flex-col gap-4">
                                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Recommended Next Action</p>
                                        
                                        <div v-if="selectedOrder.status === 'RFQ'">
                                            <button @click="prepareStatusUpdate('Submitted')" 
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                <span>Submit for Internal Review</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>
                                        <div v-else-if="selectedOrder.status === 'Submitted'">
                                            <!-- 1. Global Security Intercept: Blocks unauthorized entities completely -->
                                            <div v-if="!selectedOrder.can_approve" class="bg-amber-50 border border-amber-100 text-amber-800 rounded-xl p-4 text-center text-sm font-semibold shadow-inner">
                                                <svg class="w-6 h-6 mx-auto mb-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                <template v-if="selectedOrder.workflow_enabled">
                                                    This stage is protected by a workflow approval policy.<br/>
                                                    Awaiting authorization from assigned reviewer.
                                                </template>
                                                <template v-else>
                                                    Authorization Restricted.<br/>
                                                    Only System Administrators can perform direct approval bypasses.
                                                </template>
                                            </div>
                                            
                                            <!-- 2. Render Action Buttons ONLY to Authorized Users -->
                                            <template v-else>
                                                <!-- Special workflow action button -->
                                                <button v-if="selectedOrder.workflow_enabled" @click="prepareStatusUpdate('Approved')" 
                                                    class="w-full bg-gradient-to-r from-[#1a2b4c] to-[#2c4375] hover:from-[#e96a25] hover:to-[#ff8c42] text-white py-3.5 rounded-xl font-black text-sm shadow-lg flex items-center justify-center space-x-3 transition-all transform hover:-translate-y-0.5">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <span>Authorize Current Workflow Step</span>
                                                </button>
                                                
                                                <!-- Normal admin bypass button -->
                                                <button v-else @click="prepareStatusUpdate('Approved')" 
                                                    class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                    <span>Approve Internal Order</span>
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                </button>
                                            </template>
                                        </div>
                                        <div v-else-if="selectedOrder.status === 'Approved'">
                                            <button @click="prepareStatusUpdate('Quotation')" 
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                <span>Send Quotation to Client</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>
                                        <div v-else-if="selectedOrder.status === 'PO'">
                                            <button @click="prepareStatusUpdate('Invoiced')" 
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                <span>Confirm & Invoice</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>
                                        <div v-else-if="selectedOrder.status === 'Invoiced'">
                                            <button @click="prepareStatusUpdate('Shipped')" 
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                <span>Ship Order</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>
                                        <div v-else class="text-sm text-gray-500 italic text-center py-2">
                                            Awaiting action from client ({{ selectedOrder.status }})
                                        </div>
                                    </div>

                                    <!-- Optional Manual Override / Reject button -->
                                    <div class="flex justify-end">
                                        <button @click="prepareStatusUpdate('Rejected')" class="text-xs font-bold text-red-500 hover:text-red-700 border border-red-100 bg-red-50/50 px-3 py-1.5 rounded-lg transition">
                                            Mark as Rejected
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100 text-sm font-medium text-gray-500">
                                    This order is {{ selectedOrder.status }}. No further actions required.
                                </div>

                                <!-- Contextual Controls if something was selected -->
                                <div v-if="statusForm.status && statusForm.status !== selectedOrder.status" class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                    <!-- Rejection Textarea -->
                                    <div v-if="statusForm.status === 'Rejected'" class="mb-4">
                                        <label class="block text-xs font-bold text-red-600 mb-1 uppercase tracking-wider">Reason for Rejection <span class="text-red-500">*</span></label>
                                        <textarea 
                                            v-model="statusForm.rejection_reason" 
                                            rows="3"
                                            placeholder="Provide detailed reasoning for rejecting this requisition..."
                                            class="w-full border-red-200 bg-red-50/30 rounded-xl text-sm font-medium focus:border-red-500 focus:ring-red-500/20 placeholder:text-red-300/70"></textarea>
                                        <div v-if="statusForm.errors.rejection_reason" class="text-xs text-red-500 mt-1 font-bold">{{ statusForm.errors.rejection_reason }}</div>
                                    </div>

                                    <!-- Generic Note for any operation (Workflow signing notes etc) -->
                                    <div v-if="statusForm.status && statusForm.status !== 'Rejected'" class="mb-4">
                                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Approval Annotation / Note</label>
                                        <textarea 
                                            v-model="statusForm.note" 
                                            rows="2"
                                            placeholder="Include any relevant commentary or instruction (optional)..."
                                            class="w-full border-gray-300 rounded-xl text-sm focus:border-[#e96a25] focus:ring-[#e96a25]/20"></textarea>
                                        <div v-if="statusForm.errors.note" class="text-xs text-red-500 mt-1">{{ statusForm.errors.note }}</div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button 
                                            type="button"
                                            @click="submitStatusUpdate(selectedOrder.id)"
                                            :disabled="statusForm.processing"
                                            class="px-4 py-2 bg-[#e96a25] text-white text-sm font-bold rounded-lg hover:bg-[#d1591b] transition disabled:opacity-50 shadow-sm flex items-center"
                                        >
                                            <svg v-if="statusForm.processing" class="animate-spin h-4 w-4 mr-2 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Update to {{ statusForm.status }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 text-right">
                            <button @click="closeModal" class="px-5 py-2 text-sm font-bold text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">Close Panel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Confirmation Modal for Batch Action -->
        <ConfirmationModal 
            :show="showBatchConfirmModal" 
            title="Confirm Batch Status Update"
            :message="`Update status of ${selectedOrderIds.length} selected order(s) to ${batchForm.status}? This action will process all verified transactions according to workflow logic.`"
            type="primary"
            confirmLabel="Process Batch"
            @close="showBatchConfirmModal = false"
            @confirm="executeBatchStatusUpdate"
        />

        <!-- Alert Warning Modal -->
        <ConfirmationModal
            :show="alertModal.show"
            :title="alertModal.title"
            :message="alertModal.message"
            type="warning"
            confirmLabel="Okay"
            :hide-cancel="true"
            @confirm="alertModal.show = false"
            @close="alertModal.show = false"
        />
    </AuthenticatedLayout>
</template>
