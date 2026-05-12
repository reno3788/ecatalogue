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
});

const submitStatusUpdate = (orderId) => {
    statusForm.patch(route('admin.orders.update-status', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedOrder.value) {
                selectedOrder.value.status = statusForm.status;
                selectedOrder.value.rejection_reason = statusForm.rejection_reason;
            }
        },
    });
};

const prepareStatusUpdate = (st) => {
    statusForm.status = st;
    statusForm.rejection_reason = '';
};

// Batch Update Functionality
const selectedOrderIds = ref([]);

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

const submitBatchStatusUpdate = () => {
    if (!batchForm.status) return;
    if (batchForm.status === 'Rejected' && !batchForm.rejection_reason) {
        // Basic alert check to ensure required backend field before sending
        alert('Please enter a rejection reason for batch rejection.');
        return;
    }
    
    if (!confirm(`Update status of ${selectedOrderIds.value.length} selected orders to ${batchForm.status}?`)) {
        return;
    }

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
                        
                        <button @click="submitBatchStatusUpdate" :disabled="!batchForm.status || batchForm.processing"
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
                                                        <div class="text-xs text-gray-400 font-mono mt-0.5">{{ item.product.sku }}</div>
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
                                            <button @click="prepareStatusUpdate('Approved')" 
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                <span>Approve Internal Order</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
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
                                    <div v-if="statusForm.status === 'Rejected'" class="mb-3">
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Reason for Rejection <span class="text-red-500">*</span></label>
                                        <textarea 
                                            v-model="statusForm.rejection_reason" 
                                            rows="2"
                                            placeholder="Enter reason for rejection..."
                                            class="w-full border-gray-300 rounded-xl text-sm focus:border-red-500 focus:ring-red-500/20"></textarea>
                                        <div v-if="statusForm.errors.rejection_reason" class="text-xs text-red-500 mt-1">{{ statusForm.errors.rejection_reason }}</div>
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
    </AuthenticatedLayout>
</template>
