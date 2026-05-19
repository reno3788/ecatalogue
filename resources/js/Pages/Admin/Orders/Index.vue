<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    orders: Object,
    companies: Array,
    filters: Object,
    statuses: Array,
    carriers: Array,
});

const page = usePage();
const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val); }
    catch (e) { return `${currency} ${Number(val).toFixed(2)}`; }
};

const parseComment = (comment) => {
    if (!comment) return null;
    try {
        let parsed = comment;
        if (typeof parsed === 'string') {
            parsed = JSON.parse(parsed);
        }
        if (typeof parsed === 'string') {
            parsed = JSON.parse(parsed); // Double-decoding fallback
        }
        if (parsed && typeof parsed === 'object' && parsed.type === 'bargain') {
            return parsed;
        }
    } catch (e) {
        // Ignored, not JSON
    }
    return null;
};

const isImageAttachment = (url) => {
    if (!url) return false;
    const extension = url.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'].includes(extension);
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

const formatNumberWithCommas = (val) => {
    if (val === null || val === undefined || val === "") return "";
    const clean = String(val).replace(/[^0-9.]/g, "");
    const num = parseFloat(clean);
    if (isNaN(num)) return "";
    const currency = (page.props.appSettings?.currency || "EUR").toUpperCase();
    const isZeroDecimal = ["IDR", "JPY", "KRW", "VND", "CLP"].includes(currency);
    return new Intl.NumberFormat("en-US", {
        minimumFractionDigits: isZeroDecimal ? 0 : 2,
        maximumFractionDigits: isZeroDecimal ? 0 : 2
    }).format(num);
};

const parseNumberFromCommas = (val) => {
    if (val === null || val === undefined) return '';
    return String(val).replace(/,/g, '');
};

const getRemainingQty = (item) => {
    if (!selectedOrder.value) return 0;
    const orderQty = item.quantity;
    const shippedQty = selectedOrder.value.shipments?.reduce((sum, shipment) => {
        const shItems = shipment.items || [];
        const shItem = shItems.find(si => si.order_item_id === item.id);
        return sum + (shItem ? Number(shItem.quantity) : 0);
    }, 0) || 0;
    return Math.max(0, orderQty - shippedQty);
};

const shipmentForm = useForm({
    carrier_id: '',
    tracking_number: '',
    notes: '',
    items: []
});

const invoiceForm = useForm({
    invoice_file: null,
    additional_documents: []
});

const activeTab = ref('details');
const isSaving = computed(() => {
    return statusForm.processing || shipmentForm.processing || invoiceForm.processing || offerForm.processing || batchForm.processing;
});

const openDetails = async (orderId) => {
    activeTab.value = 'details';
    showModal.value = true;
    loadingDetails.value = true;
    try {
        const res = await axios.get(route('admin.orders.show', orderId));
        selectedOrder.value = res.data;
        
        // Initialize offerForm items if status is RFQ
        if (selectedOrder.value.status === 'RFQ') {
            offerForm.items = selectedOrder.value.items.map(item => {
                const initialPrice = item.supplier_offered_price !== null ? item.supplier_offered_price : item.price;
                return {
                    id: item.id,
                    supplier_offered_price: formatNumberWithCommas(initialPrice),
                };
            });
            offerForm.comment = '';
        }

        // Initialize shipmentForm items if status is PO or Partially Shipped
        if (['PO', 'Partially Shipped'].includes(selectedOrder.value.status)) {
            shipmentForm.items = selectedOrder.value.items.map(item => {
                const rem = getRemainingQty(item);
                return {
                    order_item_id: item.id,
                    product_name: item.product?.name || 'Deleted Product',
                    sku: item.product?.sku || '',
                    ordered_qty: item.quantity,
                    remaining_qty: rem,
                    quantity: rem,
                };
            });
            shipmentForm.carrier_id = '';
            shipmentForm.tracking_number = '';
            shipmentForm.notes = '';
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingDetails.value = false;
    }
};

const closeModal = () => {
    showModal.value = false;
    
    // Clean up URL without triggering a reload
    if (typeof window !== 'undefined') {
        const url = new URL(window.location);
        url.searchParams.delete('open_order');
        url.searchParams.delete('tab');
        url.searchParams.delete('page');
        window.history.replaceState({}, '', url);
    }

    setTimeout(() => { selectedOrder.value = null; }, 300);
};

const checkOpenOrderParameter = () => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams(window.location.search);
    const openOrderId = params.get('open_order');
    const tab = params.get('tab');
    if (openOrderId) {
        openDetails(openOrderId).then(() => {
            if (tab) activeTab.value = tab;
        });
    }
};

onMounted(() => {
    checkOpenOrderParameter();
});

watch(() => page.url, () => {
    checkOpenOrderParameter();
});

// Update Status form helper
const statusForm = useForm({
    status: '',
    rejection_reason: '',
    note: '',
    carrier_id: '',
    tracking_number: '',
});

const offerForm = useForm({
    items: [],
    comment: '',
});

const syncLatestPrices = () => {
    if (!selectedOrder.value || !selectedOrder.value.items) return;
    offerForm.items = selectedOrder.value.items.map(item => {
        let initialPrice = item.price;
        if (item.latest_sync_price !== undefined && item.latest_sync_price !== null) {
            initialPrice = item.latest_sync_price;
        } else if (item.product && item.product.base_price !== undefined) {
            initialPrice = item.product.base_price;
        }
        
        return {
            id: item.id,
            supplier_offered_price: formatNumberWithCommas(initialPrice),
        };
    });
};

const submitOffer = (orderId) => {
    const originalItems = JSON.parse(JSON.stringify(offerForm.items));
    offerForm.items = offerForm.items.map(item => {
        const cleanPrice = parseNumberFromCommas(item.supplier_offered_price);
        return {
            id: item.id,
            supplier_offered_price: cleanPrice !== '' ? parseFloat(cleanPrice) : null
        };
    });

    offerForm.post(route('admin.orders.negotiation.offer', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            openDetails(orderId);
            offerForm.reset('comment');
        },
        onError: () => {
            offerForm.items = originalItems;
        }
    });
};

const submitStatusUpdate = (orderId) => {
    statusForm.patch(route('admin.orders.update-status', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            // Complete Reload to ensure fresh workflow/can_approve states are fetched!
            openDetails(orderId);
            statusForm.reset('status', 'rejection_reason', 'note', 'carrier_id', 'tracking_number');
        },
    });
};

const submitShipment = (orderId) => {
    // Only send non-zero item quantities to avoid validation errors
    const cleanedItems = shipmentForm.items.map(i => ({
        order_item_id: i.order_item_id,
        quantity: parseInt(i.quantity) || 0
    }));

    shipmentForm.transform((data) => ({
        ...data,
        items: cleanedItems
    })).post(route('admin.orders.create-shipment', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            openDetails(orderId);
            shipmentForm.reset();
        }
    });
};

const submitInvoiceUpload = (orderId) => {
    invoiceForm.post(route('admin.orders.upload-invoice', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            openDetails(orderId);
            invoiceForm.reset();
        }
    });
};

const prepareStatusUpdate = (st) => {
    statusForm.status = st;
    statusForm.rejection_reason = '';
    statusForm.note = '';
    statusForm.carrier_id = '';
    statusForm.tracking_number = '';
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
                                    <button @click="openDetails(order.id)" class="font-black text-[#1a2b4c] hover:text-[#e96a25] transition-colors border-b border-dashed border-gray-300 hover:border-[#e96a25] text-left">
                                        #{{ String(order.id).padStart(6, '0') }}
                                    </button>
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
                                    <div class="flex items-center justify-end space-x-2">
                                        <a v-if="order.po_attachment" 
                                            :href="order.po_attachment" 
                                            target="_blank"
                                            title="View PO Document"
                                            class="bg-violet-50 border border-violet-200 text-violet-700 hover:bg-violet-100 px-3 py-1.5 rounded-lg text-xs font-extrabold shadow-sm transition duration-200 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span></span>
                                        </a>
                                        <button @click="openDetails(order.id)" 
                                            class="bg-white border border-gray-200 text-[#1a2b4c] hover:border-[#e96a25] hover:text-[#e96a25] px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition duration-200 whitespace-nowrap">
                                            View / Update
                                        </button>
                                    </div>
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

    </AuthenticatedLayout>

    <!-- Global Processing Overlay -->
    <div v-if="statusForm.processing || batchForm.processing || invoiceForm.processing || offerForm.processing" class="fixed inset-0 z-[9999] bg-white/40 backdrop-blur-sm flex flex-col items-center justify-center transition-all duration-300">
        <div class="flex flex-col items-center space-y-4 bg-white shadow-2xl rounded-2xl px-12 py-10 border border-gray-100 mx-4">
            <div class="relative flex items-center justify-center">
                <div class="w-16 h-16 border-4 border-indigo-50 border-t-[#e96a25] rounded-full animate-spin"></div>
                <svg class="w-8 h-8 text-[#1a2b4c] absolute" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <div class="text-center">
                <h3 class="text-lg font-black text-[#1a2b4c] tracking-tight uppercase">System Processing</h3>
                <p class="text-sm text-gray-500 font-bold mt-1">Updating order status and notifying stakeholders...</p>
                <div class="flex items-center justify-center gap-1 mt-4">
                    <div class="w-1.5 h-1.5 bg-[#e96a25] rounded-full animate-bounce" style="animation-delay: 0s"></div>
                    <div class="w-1.5 h-1.5 bg-[#e96a25] rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    <div class="w-1.5 h-1.5 bg-[#e96a25] rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                </div>
            </div>
        </div>
    </div>

        <!-- Details & Status Management Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay -->
                <div @click="closeModal" class="fixed inset-0 bg-[#1a2b4c]/40 backdrop-blur-sm transition-opacity"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full border border-gray-100 relative">
                    
                    <!-- Global Process Loading Overlay -->
                    <div v-if="isSaving" class="absolute inset-0 bg-white/70 backdrop-blur-md z-50 flex flex-col items-center justify-center space-y-4 transition-all duration-300">
                        <div class="relative flex items-center justify-center">
                            <div class="absolute w-20 h-20 rounded-full border-4 border-[#e96a25]/20 animate-ping"></div>
                            <div class="absolute w-16 h-16 rounded-full border-4 border-t-[#e96a25] border-r-transparent border-b-transparent border-l-transparent animate-spin duration-700"></div>
                            <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-[#e96a25] to-orange-400 flex items-center justify-center text-white shadow-lg relative z-10">
                                <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.2" />
                                </svg>
                            </div>
                        </div>
                        <h4 class="text-sm font-black text-[#1a2b4c] uppercase tracking-wider animate-pulse mt-2">Processing Transaction...</h4>
                        <p class="text-[11px] text-gray-500 font-bold">Deploying B2B notifications. Please wait.</p>
                    </div>
                    
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

                        <!-- Tabs Bar Navigation -->
                        <div class="flex border-b border-gray-100 bg-gray-50/30 px-6 overflow-x-auto select-none no-scrollbar">
                            <button 
                                @click="activeTab = 'details'"
                                :class="[
                                    'py-3.5 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition flex items-center gap-1.5 whitespace-nowrap',
                                    activeTab === 'details' 
                                        ? 'border-[#e96a25] text-[#e96a25]' 
                                        : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-200'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span>Order Details</span>
                            </button>
                            
                            <button 
                                @click="activeTab = 'shipping'"
                                :class="[
                                    'py-3.5 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition flex items-center gap-1.5 whitespace-nowrap',
                                    activeTab === 'shipping' 
                                        ? 'border-[#e96a25] text-[#e96a25]' 
                                        : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-200'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                <span>Fulfillment & Shipping</span>
                                <span v-if="['PO', 'Partially Shipped'].includes(selectedOrder.status)" class="w-2 h-2 rounded-full bg-[#e96a25] animate-ping"></span>
                            </button>

                            <button 
                                @click="activeTab = 'invoices'"
                                :class="[
                                    'py-3.5 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition flex items-center gap-1.5 whitespace-nowrap',
                                    activeTab === 'invoices' 
                                        ? 'border-[#e96a25] text-[#e96a25]' 
                                        : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-200'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span>Invoices & Billing</span>
                                <span v-if="selectedOrder.status === 'Shipped'" class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            </button>

                            <button 
                                @click="activeTab = 'history'"
                                :class="[
                                    'py-3.5 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition flex items-center gap-1.5 whitespace-nowrap',
                                    activeTab === 'history' 
                                        ? 'border-[#e96a25] text-[#e96a25]' 
                                        : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-200'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Activity & Logs</span>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-6">
                            
                            <!-- TAB 1: DETAILS -->
                            <div v-if="activeTab === 'details'" class="space-y-6 animate-fadeIn">
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

                                <!-- PO Attachment Link Section (Uploaded by Buyer) -->
                                <div v-if="selectedOrder.po_attachment" class="bg-violet-50/50 border border-violet-100 rounded-xl p-4 flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center text-violet-600 flex-shrink-0 shadow-sm">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div class="truncate pr-2">
                                            <div class="text-xs font-black text-[#1a2b4c] uppercase tracking-widest flex items-center gap-1.5">
                                                Buyer-Signed PO
                                                <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-[9px] rounded-full font-bold">Verified</span>
                                            </div>
                                            <div class="text-[11px] text-violet-700 font-semibold truncate mt-0.5 max-w-[300px]">{{ selectedOrder.po_attachment.split('/').pop() }}</div>
                                        </div>
                                    </div>
                                    <a 
                                        :href="selectedOrder.po_attachment" 
                                        target="_blank"
                                        class="px-4 py-2 bg-white border border-violet-200 text-violet-700 text-xs font-black rounded-xl hover:bg-violet-50 transition duration-200 shadow-sm hover:shadow-md flex items-center gap-1.5 flex-shrink-0"
                                    >
                                        <span>Open Document</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                </div>

                                <!-- Clickable Thumbnail Preview for Photo PO -->
                                <div v-if="selectedOrder.po_attachment && isImageAttachment(selectedOrder.po_attachment)" class="mt-4">
                                    <div class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-2">Document Preview</div>
                                    <a :href="selectedOrder.po_attachment" target="_blank" class="inline-block group relative border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all max-w-sm">
                                        <img :src="selectedOrder.po_attachment" class="w-full h-auto max-h-64 object-cover hover:scale-105 transition-transform duration-300" alt="PO Attachment Image" />
                                        <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Expand View
                                        </div>
                                    </a>
                                </div>

                                <!-- Rejection Reason if present -->
                                <div v-if="selectedOrder.status === 'Rejected' && selectedOrder.rejection_reason" class="bg-red-50/50 border border-red-100 rounded-xl p-4 text-sm text-red-800">
                                    <div class="font-bold text-xs uppercase tracking-wider text-red-600 mb-1">Rejection Reason</div>
                                    "{{ selectedOrder.rejection_reason }}"
                                </div>

                                <!-- Items table -->
                                <div>
                                    <h4 class="font-bold text-sm text-gray-700 mb-3 flex items-center justify-between">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                            Ordered Items
                                        </span>
                                        <button 
                                            v-if="selectedOrder.status === 'RFQ'"
                                            type="button" 
                                            @click="syncLatestPrices" 
                                            class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-600 hover:text-white border border-indigo-200 hover:bg-indigo-600 rounded-lg transition"
                                        >
                                            Sync Latest Prices
                                        </button>
                                    </h4>
                                    <div class="border border-gray-100 rounded-xl overflow-hidden">
                                        <table class="w-full text-left text-sm">
                                            <thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase">
                                                <tr>
                                                    <th class="px-4 py-3">Product</th>
                                                    <th class="px-4 py-3 text-right">Qty</th>
                                                    <th class="px-4 py-3 text-right">Price Info</th>
                                                    <th v-if="selectedOrder.status === 'RFQ'" class="px-4 py-3 text-right w-44">Offered Price ({{ page.props.appSettings?.currency || 'EUR' }})</th>
                                                    <th class="px-4 py-3 text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50">
                                                <tr v-for="(item, idx) in selectedOrder.items" :key="item.id">
                                                    <td class="px-4 py-3">
                                                        <template v-if="item.product">
                                                            <Link :href="route('catalog.show', { product: item.product.id, origin: 'admin-orders', order_id: selectedOrder.id })" class="font-bold text-[#1a2b4c] hover:text-[#e96a25] hover:underline transition">
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
                                                    <td class="px-4 py-3 text-right text-xs space-y-1">
                                                        <div class="text-gray-500 font-medium">Original: {{ formatCurrency(item.original_price ?? item.price) }}</div>
                                                        <div v-if="item.supplier_offered_price" class="text-indigo-600 font-extrabold bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded inline-block text-[10px] uppercase tracking-wider">
                                                            Offered: {{ formatCurrency(item.supplier_offered_price) }}
                                                        </div>
                                                        <div v-if="item.buyer_requested_price" class="text-[#e96a25] font-extrabold bg-[#e96a25]/5 border border-[#e96a25]/15 px-2 py-0.5 rounded inline-block text-[10px] uppercase tracking-wider">
                                                            Bargained: {{ formatCurrency(item.buyer_requested_price) }}
                                                        </div>
                                                    </td>
                                                    <td v-if="selectedOrder.status === 'RFQ'" class="px-4 py-3 text-right">
                                                        <div class="flex items-center justify-end">
                                                            <input 
                                                                v-if="offerForm.items[idx]"
                                                                type="text" 
                                                                :value="offerForm.items[idx].supplier_offered_price"
                                                                @focus="offerForm.items[idx].supplier_offered_price = parseNumberFromCommas(offerForm.items[idx].supplier_offered_price)"
                                                                @blur="offerForm.items[idx].supplier_offered_price = formatNumberWithCommas($event.target.value)"
                                                                @input="offerForm.items[idx].supplier_offered_price = $event.target.value"
                                                                class="w-32 text-right bg-gray-50/50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold text-gray-800 focus:bg-white focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 transition shadow-inner"
                                                            />
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-right font-bold text-[#1a2b4c]">
                                                        <span v-if="selectedOrder.status === 'RFQ' && offerForm.items[idx]">
                                                            {{ formatCurrency(Number(parseNumberFromCommas(offerForm.items[idx].supplier_offered_price || 0)) * item.quantity) }}
                                                        </span>
                                                        <span v-else>
                                                            {{ formatCurrency((item.supplier_offered_price !== null ? item.supplier_offered_price : item.price) * item.quantity) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Standard Action Buttons & Workflow State Management -->
                                <div class="pt-4 border-t border-gray-100">
                                    <h4 class="font-bold text-sm text-gray-700 mb-3">Order Status & Core Actions</h4>
                                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Workflow Action Panel</p>
                                        
                                        <!-- RFQ state action -->
                                        <div v-if="selectedOrder.status === 'RFQ'">
                                            <div class="mb-4">
                                                <div class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg p-3 mb-4 font-semibold">
                                                    💡 Prepare your Quotation Offer by reviewing and adjusting the "Offered Price" per item in the table above, then enter comments below to submit the offer back to the buyer.
                                                </div>
                                                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Offer Comment / Negotiation Notes <span class="text-red-500">*</span></label>
                                                <textarea 
                                                    v-model="offerForm.comment" 
                                                    rows="3"
                                                    placeholder="Provide details about your offered prices, discount availability, delivery timelines, etc..."
                                                    class="w-full border-gray-200 bg-white rounded-xl text-sm font-medium focus:border-indigo-500 focus:ring-indigo-500/20 placeholder:text-gray-300"></textarea>
                                                <div v-if="offerForm.errors.comment" class="text-xs text-red-500 mt-1 font-bold">{{ offerForm.errors.comment }}</div>
                                            </div>
                                            <button 
                                                type="button"
                                                @click="submitOffer(selectedOrder.id)" 
                                                :disabled="offerForm.processing"
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition disabled:opacity-50">
                                                <svg v-if="offerForm.processing" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                <span>Submit Quotation Offer</span>
                                                <svg v-if="!offerForm.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>

                                        <!-- Submitted state actions -->
                                        <div v-else-if="selectedOrder.status === 'Submitted'">
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
                                            <template v-else>
                                                <button v-if="selectedOrder.workflow_enabled" @click="prepareStatusUpdate('Approved')" 
                                                    class="w-full bg-gradient-to-r from-[#1a2b4c] to-[#2c4375] hover:from-[#e96a25] hover:to-[#ff8c42] text-white py-3.5 rounded-xl font-black text-sm shadow-lg flex items-center justify-center space-x-3 transition-all transform hover:-translate-y-0.5">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <span>Authorize Current Workflow Step</span>
                                                </button>
                                                <button v-else @click="prepareStatusUpdate('Approved')" 
                                                    class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                    <span>Approve Internal Order</span>
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                </button>
                                            </template>
                                        </div>

                                        <!-- Approved state actions -->
                                        <div v-else-if="selectedOrder.status === 'Approved'">
                                            <button @click="prepareStatusUpdate('Quotation')" 
                                                class="w-full bg-[#1a2b4c] hover:bg-[#111d33] text-white py-3 rounded-xl font-black text-sm shadow-sm flex items-center justify-center space-x-2 transition">
                                                <span>Send Quotation to Client</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>

                                        <!-- Direct Link / Guidance for Shipping state -->
                                        <div v-else-if="['PO', 'Partially Shipped'].includes(selectedOrder.status)">
                                            <div class="text-center py-2">
                                                <div class="text-xs text-gray-500 font-semibold mb-2">📦 This order is active and ready to be processed for shipping dispatch.</div>
                                                <button @click="activeTab = 'shipping'" class="px-4 py-2 bg-[#e96a25] text-white font-black text-xs uppercase tracking-wider rounded-xl shadow hover:bg-[#d0591b] transition">
                                                    Go to Fulfillment & Shipping Tab
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Direct Link / Guidance for Shipped state -->
                                        <div v-else-if="selectedOrder.status === 'Shipped'">
                                            <div class="text-center py-2">
                                                <div class="text-xs text-gray-500 font-semibold mb-2">🧾 The shipment has been dispatched. Ready for commercial invoice upload.</div>
                                                <button @click="activeTab = 'invoices'" class="px-4 py-2 bg-emerald-500 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow hover:bg-emerald-600 transition">
                                                    Go to Invoices & Billing Tab
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Complete action for Invoiced state -->
                                        <div v-else-if="selectedOrder.status === 'Invoiced'">
                                            <div class="text-center py-2 space-y-3">
                                                <div class="text-xs text-gray-500 font-semibold mb-2">🧾 The commercial invoice and supporting docs have been uploaded. Ready to finalize and complete this order.</div>
                                                <button @click="prepareStatusUpdate('Completed')" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-sm hover:shadow transition flex items-center justify-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    Complete Order
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Completed / Rejected or other neutral status states -->
                                        <div v-else class="text-sm text-gray-500 font-bold text-center py-1">
                                            This order is in status <span class="text-[#1a2b4c] font-black">{{ selectedOrder.status }}</span>. No active administrative decisions required.
                                        </div>
                                    </div>
                                </div>

                                <!-- Contextual statusForm Controls (e.g. rejection reason / approval notes inputs) -->
                                <div v-if="statusForm.status && statusForm.status !== selectedOrder.status" class="bg-gray-50 rounded-xl p-4 border border-gray-200 mt-4">
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

                                    <!-- Carrier Selection & Tracking for Shipped -->
                                    <div v-if="statusForm.status === 'Shipped'" class="mb-4 space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Select Shipping Carrier <span class="text-red-500">*</span></label>
                                            <select 
                                                v-model="statusForm.carrier_id" 
                                                class="w-full border-gray-200 rounded-xl text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm bg-white font-medium"
                                                required
                                            >
                                                <option value="" disabled selected>-- Choose Carrier --</option>
                                                <option v-for="carrier in props.carriers" :key="carrier.id" :value="carrier.id">
                                                    {{ carrier.name }}
                                                </option>
                                            </select>
                                            <div v-if="statusForm.errors.carrier_id" class="text-xs text-red-500 mt-1 font-bold">{{ statusForm.errors.carrier_id }}</div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Tracking / Waybill Number <span class="text-red-500">*</span></label>
                                            <input 
                                                type="text" 
                                                v-model="statusForm.tracking_number" 
                                                placeholder="e.g. TRK123456789"
                                                class="w-full border-gray-200 rounded-xl text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm font-semibold"
                                                required
                                            />
                                            <div v-if="statusForm.errors.tracking_number" class="text-xs text-red-500 mt-1 font-bold">{{ statusForm.errors.tracking_number }}</div>
                                        </div>
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

                            <!-- TAB 2: FULFILLMENT & SHIPPING -->
                            <div v-if="activeTab === 'shipping'" class="space-y-6 animate-fadeIn">
                                <!-- Shipment Tracking Card -->
                                <div v-if="selectedOrder.carrier || selectedOrder.tracking_number" class="bg-gradient-to-r from-[#e96a25]/5 to-orange-50 border border-[#e96a25]/10 rounded-xl p-4 space-y-3">
                                    <div class="flex items-center justify-between border-b border-[#e96a25]/10 pb-2">
                                        <h4 class="font-extrabold text-xs text-[#e96a25] uppercase tracking-wider flex items-center">
                                            <svg class="w-4 h-4 mr-1 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                            Active Carrier Dispatch Details
                                        </h4>
                                        <span class="px-2 py-0.5 bg-[#e96a25] text-white text-[9px] rounded-full font-black uppercase tracking-widest">
                                            {{ selectedOrder.status }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="text-xs font-bold text-gray-400 block uppercase tracking-wider">Carrier Service</span>
                                            <span class="font-black text-[#1a2b4c] text-sm">{{ selectedOrder.carrier?.name || 'Manual/Custom Delivery' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs font-bold text-gray-400 block uppercase tracking-wider">Tracking Reference</span>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="font-mono font-black text-gray-800">{{ selectedOrder.tracking_number || 'N/A' }}</span>
                                                
                                                <a v-if="selectedOrder.carrier?.tracking_url_pattern && selectedOrder.tracking_number"
                                                    :href="selectedOrder.carrier.tracking_url_pattern.replace('{tracking_number}', selectedOrder.tracking_number)"
                                                    target="_blank"
                                                    class="px-2 py-0.5 bg-[#e96a25]/10 hover:bg-[#e96a25]/20 text-[#e96a25] text-[10px] font-black rounded border border-[#e96a25]/20 flex items-center gap-1 transition duration-150"
                                                >
                                                    <span>Track Link</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shipments & Delivery Tracking List -->
                                <div v-if="selectedOrder.shipments && selectedOrder.shipments.length > 0">
                                    <h4 class="font-black text-xs uppercase tracking-wider text-gray-400 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                        Dispatched Shipments Timeline
                                    </h4>
                                    <div class="bg-orange-50/20 rounded-xl border border-orange-100/30 p-5 space-y-6">
                                        <div class="flow-root">
                                            <ul role="list" class="-mb-8">
                                                <li v-for="(shipment, shipIdx) in selectedOrder.shipments" :key="shipment.id">
                                                    <div class="relative pb-8">
                                                        <span v-if="shipIdx !== selectedOrder.shipments.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-orange-100" aria-hidden="true"></span>
                                                        
                                                        <div class="relative flex space-x-3">
                                                            <div>
                                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white bg-orange-100 text-orange-600">
                                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1 flex justify-between space-x-4">
                                                                <div class="space-y-1.5">
                                                                    <p class="text-xs text-gray-500">
                                                                        <span class="font-bold text-gray-900">Shipment #{{ shipment.id }}</span> via 
                                                                        <span class="font-semibold text-gray-800">{{ shipment.carrier?.name || 'Unknown Carrier' }}</span>
                                                                    </p>
                                                                    <div class="flex flex-wrap items-center gap-2">
                                                                        <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-white border border-gray-200 rounded text-gray-700">
                                                                            Waybill: {{ shipment.tracking_number }}
                                                                        </span>
                                                                        <a v-if="shipment.carrier?.tracking_url" 
                                                                           :href="shipment.carrier.tracking_url.replace('{tracking_number}', shipment.tracking_number)" 
                                                                           target="_blank" 
                                                                           class="text-[10px] font-bold text-[#e96a25] hover:underline flex items-center">
                                                                            <span>Track Package</span>
                                                                            <svg class="w-3.5 h-3.5 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                                                        </a>
                                                                        <a :href="'/storage/' + shipment.delivery_note_path" 
                                                                           target="_blank" 
                                                                           class="text-[10px] font-bold text-indigo-600 hover:underline flex items-center">
                                                                            <span>Delivery Note (PDF)</span>
                                                                            <svg class="w-3.5 h-3.5 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                                        </a>
                                                                    </div>
                                                                    <!-- Display shipped items -->
                                                                    <div class="text-[11px] text-gray-600 bg-white border border-gray-100 rounded-lg p-2.5 space-y-1 shadow-sm font-medium">
                                                                        <div class="font-bold text-gray-400 uppercase text-[9px] tracking-wider mb-1">Shipped Items</div>
                                                                        <div v-for="si in shipment.items" :key="si.id" class="flex justify-between gap-4">
                                                                            <span class="truncate max-w-[260px]">{{ si.order_item?.product?.name || 'Item' }}</span>
                                                                            <span class="font-bold whitespace-nowrap">Qty: {{ si.quantity }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="text-right text-xs whitespace-nowrap text-gray-400 font-medium">
                                                                    {{ new Date(shipment.created_at).toLocaleDateString() }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Standalone Shipment Redirect Button -->
                                <div v-if="['PO', 'Partially Shipped'].includes(selectedOrder.status)" class="border-t border-gray-100 pt-6">
                                    <div class="bg-[#e96a25]/5 rounded-2xl p-6 border border-[#e96a25]/10 space-y-4 flex flex-col md:flex-row md:items-center justify-between gap-6">
                                        <div class="space-y-1.5 max-w-md text-left">
                                            <div class="flex items-center space-x-2">
                                                <span class="p-1.5 bg-[#e96a25]/10 text-[#e96a25] rounded-xl">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                                </span>
                                                <h4 class="font-black text-sm text-gray-800">Dispatch Batch Shipment</h4>
                                            </div>
                                            <p class="text-xs text-gray-500 font-medium leading-relaxed">
                                                To prevent clutter and keep calculations perfectly precise, shipment creation is now managed on a dedicated standalone page. Adjust batch packing quantities, set carrier details, and generate Delivery Notes.
                                            </p>
                                        </div>
                                        <Link 
                                            :href="route('admin.orders.create-shipment-form', selectedOrder.id)"
                                            class="inline-flex items-center justify-center px-5 py-3.5 rounded-xl text-xs font-black uppercase tracking-wider text-white bg-[#e96a25] hover:bg-[#d0591b] shadow active:scale-95 transition shrink-0 space-x-2"
                                        >
                                            <span>Start Dispatch Process</span>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        </Link>
                                    </div>
                                </div>

                                <!-- Empty State for no shipments and no shipment actions -->
                                <div v-if="!['PO', 'Partially Shipped'].includes(selectedOrder.status) && (!selectedOrder.shipments || selectedOrder.shipments.length === 0)" 
                                     class="flex flex-col items-center justify-center py-10 bg-gray-50 border border-dashed border-gray-200 rounded-xl space-y-3">
                                    <div class="w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    </div>
                                    <div class="text-sm font-bold text-gray-500">No Shipping Records Found</div>
                                    <p class="text-xs text-gray-400 font-medium max-w-sm text-center">Fulfillment triggers and tracking timelines become active once the purchase order is approved and locked.</p>
                                </div>
                            </div>

                            <!-- TAB 3: INVOICES & BILLING -->
                            <div v-if="activeTab === 'invoices'" class="space-y-6 animate-fadeIn">
                                <!-- Invoice & Commercial Documents -->
                                <div v-if="selectedOrder.invoice_attachment || (selectedOrder.invoice_documents && selectedOrder.invoice_documents.length > 0)">
                                    <h4 class="font-black text-xs uppercase tracking-wider text-gray-400 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Uploaded Financial Invoices & Records
                                    </h4>
                                    <div class="bg-emerald-50/20 rounded-xl border border-emerald-100/30 p-5 space-y-3">
                                        <div v-if="selectedOrder.invoice_attachment" class="flex items-center justify-between bg-white border border-gray-100 p-3 rounded-lg shadow-sm">
                                            <div class="flex items-center space-x-3">
                                                <svg class="w-8 h-8 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A1 1 0 0112 2.586L15.414 6A1 1 0 0116 6.707V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 012 0h4a1 1 0 110 2H8a1 1 0 01-2-1z" clip-rule="evenodd"/></svg>
                                                <div>
                                                    <div class="text-xs font-bold text-gray-800">Commercial Invoice (Signed)</div>
                                                    <div class="text-[10px] text-gray-400 font-mono">Verified B2B Settlement Record</div>
                                                </div>
                                            </div>
                                            <a :href="`/storage/${selectedOrder.invoice_attachment}`" target="_blank" class="px-3.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-black rounded-lg transition flex items-center space-x-1">
                                                <span>Download PDF</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </a>
                                        </div>
                                        
                                        <div v-if="selectedOrder.invoice_documents && selectedOrder.invoice_documents.length > 0" class="space-y-2 pt-2">
                                            <div class="text-[10px] font-black text-gray-400 uppercase tracking-wider">Supporting Documentation Files</div>
                                            <div v-for="doc in selectedOrder.invoice_documents" :key="doc.path" class="flex items-center justify-between bg-white border border-gray-100 p-3 rounded-lg shadow-sm">
                                                <div class="flex items-center space-x-3">
                                                    <svg class="w-7 h-7 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A1 1 0 0016.586 6L14 3.414A1 1 0 0013.293 3H9z"/><path d="M5 6a1 1 0 00-1 1v8a3 3 0 003 3h7a1 1 0 100-2H7a1 1 0 01-1-1V7a1 1 0 00-1-1z"/></svg>
                                                    <div class="text-xs font-semibold text-gray-850 truncate max-w-sm">{{ doc.name }}</div>
                                                </div>
                                                <a :href="`/storage/${doc.path}`" target="_blank" class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-lg transition flex items-center space-x-1">
                                                    <span>Download</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Invoice Upload Form (Supplier Side) -->
                                <div v-if="['Shipped'].includes(selectedOrder.status)" class="border-t border-gray-100 pt-4">
                                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 space-y-4">
                                        <div class="flex items-center space-x-2">
                                            <span class="p-1.5 bg-emerald-50 text-emerald-700 rounded-lg">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            </span>
                                            <h4 class="font-black text-sm text-gray-800">Upload Financial Commercial Invoice</h4>
                                        </div>
                                        
                                        <div class="text-xs text-emerald-700 bg-emerald-50/50 border border-emerald-100/50 rounded-lg p-3 font-semibold">
                                            🧾 Delivery has been completed! Please upload the final B2B commercial invoice and any supplemental proof of delivery (POD) documents to close out and finalize this order.
                                        </div>
                                        
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Main Commercial Invoice File (PDF, PNG, JPG) <span class="text-red-500">*</span></label>
                                                <input 
                                                    type="file" 
                                                    @input="invoiceForm.invoice_file = $event.target.files[0]"
                                                    class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-[#1a2b4c] file:text-white hover:file:bg-[#111d33] transition"
                                                    required
                                                />
                                                <div v-if="invoiceForm.errors.invoice_file" class="text-xs text-red-500 mt-1 font-bold">{{ invoiceForm.errors.invoice_file }}</div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Additional Supporting Documentation (Optional)</label>
                                                <input 
                                                    type="file" 
                                                    multiple
                                                    @input="invoiceForm.additional_documents = Array.from($event.target.files)"
                                                    class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-250 transition"
                                                />
                                                <div v-if="invoiceForm.errors.additional_documents" class="text-xs text-red-500 mt-1 font-bold">{{ invoiceForm.errors.additional_documents }}</div>
                                            </div>
                                        </div>

                                        <button 
                                            type="button"
                                            @click="submitInvoiceUpload(selectedOrder.id)" 
                                            :disabled="invoiceForm.processing"
                                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3.5 rounded-xl font-black text-sm shadow flex items-center justify-center space-x-2 transition disabled:opacity-50">
                                            <svg v-if="invoiceForm.processing" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span>Upload Invoice & Finalize Order Transaction</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Empty State for no invoices -->
                                <div v-if="!selectedOrder.invoice_attachment && (!selectedOrder.invoice_documents || selectedOrder.invoice_documents.length === 0) && !['Shipped'].includes(selectedOrder.status)" 
                                     class="flex flex-col items-center justify-center py-10 bg-gray-50 border border-dashed border-gray-200 rounded-xl space-y-3 animate-fadeIn">
                                    <div class="w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <div class="text-sm font-bold text-gray-500">No Billing Documents Uploaded</div>
                                    <p class="text-xs text-gray-400 font-medium max-w-sm text-center">Billing, settlement invoices, and supplemental financial documents are recorded here after the supplier dispatches the shipments.</p>
                                </div>
                            </div>

                            <!-- TAB 4: ACTIVITY & LOGS -->
                            <div v-if="activeTab === 'history'" class="space-y-6 animate-fadeIn">
                                <!-- Negotiation & Comment History -->
                                <div v-if="selectedOrder.histories && selectedOrder.histories.length > 0">
                                    <h4 class="font-black text-xs uppercase tracking-wider text-gray-400 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                        Quotation Offers & Negotiation Comments
                                    </h4>
                                    <div class="bg-indigo-50/20 rounded-xl border border-indigo-100/30 p-5 space-y-4">
                                        <div class="flow-root">
                                            <ul role="list" class="-mb-8">
                                                <li v-for="(hist, histIdx) in selectedOrder.histories" :key="hist.id">
                                                    <div class="relative pb-8">
                                                        <span v-if="histIdx !== selectedOrder.histories.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-indigo-100" aria-hidden="true"></span>
                                                        
                                                        <div class="relative flex space-x-3">
                                                            <div>
                                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white bg-indigo-100 text-indigo-600">
                                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1 flex justify-between space-x-4">
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">
                                                                        <span class="font-black text-gray-900">{{ hist.user?.name || 'System' }}</span>
                                                                        <span class="mx-1 text-gray-400">updated state:</span>
                                                                        <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider bg-gray-100 rounded-md text-gray-700">{{ hist.status_before }}</span>
                                                                        <span class="mx-0.5 text-gray-400">→</span>
                                                                        <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider bg-[#1a2b4c]/5 rounded-md text-[#1a2b4c]">{{ hist.status_after }}</span>
                                                                    </p>
                                                                    <div v-if="hist.comment" class="mt-2 text-xs">
                                                                        <template v-if="parseComment(hist.comment)">
                                                                            <p class="text-gray-700 bg-white border border-gray-100 rounded-lg p-2.5 font-medium shadow-sm italic">
                                                                                "{{ parseComment(hist.comment).text }}"
                                                                            </p>
                                                                            
                                                                            <div class="mt-2 bg-indigo-50/40 rounded-lg border border-indigo-100/30 p-3 space-y-2">
                                                                                <div class="text-[9px] font-black text-indigo-700 uppercase tracking-wider mb-1.5 flex items-center space-x-1">
                                                                                    <svg class="h-3 w-3 text-indigo-600 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                                                    </svg>
                                                                                    <span>Bargained Items Details:</span>
                                                                                </div>
                                                                                <div v-for="(item, itemIdx) in parseComment(hist.comment).items" :key="itemIdx" class="flex justify-between items-center text-gray-600 bg-white/80 rounded-lg p-2 border border-indigo-100/20 shadow-xs">
                                                                                    <div class="font-bold text-gray-800 text-[11px]">{{ item.name }}</div>
                                                                                    <div class="text-right space-x-2 text-[10px] flex items-center">
                                                                                        <span class="text-gray-400">Offered:</span>
                                                                                        <span class="font-semibold text-gray-500 line-through">{{ formatCurrency(item.offered_price) }}</span>
                                                                                        <span class="text-indigo-500 font-medium">→</span>
                                                                                        <span class="text-indigo-700 font-bold bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100/30">Bargain: {{ formatCurrency(item.bargain_price) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                        <template v-else>
                                                                            <p class="text-gray-700 bg-white border border-gray-100 rounded-lg p-2.5 font-medium shadow-sm italic">
                                                                                "{{ hist.comment }}"
                                                                            </p>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                                <div class="text-right text-xs whitespace-nowrap text-gray-400 font-medium">
                                                                    {{ new Date(hist.created_at).toLocaleDateString() }}
                                                                    {{ new Date(hist.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Approval History / Logs -->
                                <div v-if="selectedOrder.approval_logs && selectedOrder.approval_logs.length > 0">
                                    <h4 class="font-black text-xs uppercase tracking-wider text-gray-400 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                        Workflow Approvals & State Signatures
                                    </h4>
                                    <div class="bg-gray-50/50 rounded-xl border border-gray-100 p-5 space-y-4">
                                        <div class="flow-root">
                                            <ul role="list" class="-mb-8">
                                                <li v-for="(log, logIdx) in selectedOrder.approval_logs" :key="log.id">
                                                    <div class="relative pb-8">
                                                        <span v-if="logIdx !== selectedOrder.approval_logs.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                                        
                                                        <div class="relative flex space-x-3">
                                                            <div>
                                                                <span :class="[
                                                                    'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                                                                    log.action.toLowerCase().includes('approve') ? 'bg-emerald-100 text-emerald-600' : (log.action.toLowerCase().includes('reject') ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600')
                                                                ]">
                                                                    <svg v-if="log.action.toLowerCase().includes('approve')" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                                    <svg v-else-if="log.action.toLowerCase().includes('reject')" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                                    <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1 flex justify-between space-x-4">
                                                                <div>
                                                                    <p class="text-xs text-gray-500 font-medium">
                                                                        <span class="font-black text-gray-900">{{ log.user?.name || 'System' }}</span> 
                                                                        <span class="mx-1 uppercase tracking-wide text-[10px] font-black" :class="log.action.toLowerCase().includes('approve') ? 'text-emerald-600' : (log.action.toLowerCase().includes('reject') ? 'text-red-600' : 'text-gray-600')">{{ log.action }}</span> 
                                                                        this order transaction.
                                                                    </p>
                                                                    <p v-if="log.note" class="text-xs italic text-gray-500 mt-1.5 border-l-2 border-gray-250 pl-2 bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
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

                                <!-- Empty State for History and Logs -->
                                <div v-if="(!selectedOrder.histories || selectedOrder.histories.length === 0) && (!selectedOrder.approval_logs || selectedOrder.approval_logs.length === 0)" 
                                     class="flex flex-col items-center justify-center py-10 bg-gray-50 border border-dashed border-gray-200 rounded-xl space-y-3 animate-fadeIn">
                                    <div class="w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <div class="text-sm font-bold text-gray-500">No History Recorded Yet</div>
                                    <p class="text-xs text-gray-400 font-medium max-w-sm text-center">Audit logs and collaboration timestamps will be logged here as order negotiations progress.</p>
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
</template>
