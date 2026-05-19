<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    orders: Object,
    filters: Object,
    statuses: Array,
    summary: Object, // Explicitly injected summary from controller
});

const page = usePage();

// Logic: Show success alerts dynamically via shared flash state
const successMessage = ref(page.props.flash?.success || '');
const showSuccessAlert = ref(!!successMessage.value);
const dismissAlert = () => { showSuccessAlert.value = false; };

const pendingApprovals = computed(() => page.props.pendingApprovals || []);
const pendingRfqs = computed(() => page.props.pendingRfqs || []);
const totalTasksCount = computed(() => pendingApprovals.value.length + pendingRfqs.value.length);

const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val); }
    catch (e) { return `${currency} ${Number(val).toFixed(2)}`; }
};

const isImageAttachment = (url) => {
    if (!url) return false;
    const extension = url.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'].includes(extension);
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

// Filter states
const filterMonth = ref(props.filters.month || '');
const filterStatus = ref(props.filters.status || '');

// Inertia Watch reload for automatic filtering behavior on main dash
watch([filterMonth, filterStatus], () => {
    router.get(route('dashboard'), {
        month: filterMonth.value,
        status: filterStatus.value,
    }, {
        preserveState: true,
        replace: true,
    });
});

const resetFilters = () => {
    filterMonth.value = '';
    filterStatus.value = '';
};

const toggleStatusFilter = (statusVal) => {
    if (filterStatus.value === statusVal) {
        filterStatus.value = '';
    } else {
        filterStatus.value = statusVal;
    }
};

// --- Premium Custom Month Picker Logic ---
const isPickerOpen = ref(false);
const pickerYear = ref(new Date().getFullYear());
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const openPicker = () => {
    if (filterMonth.value) {
        pickerYear.value = parseInt(filterMonth.value.split('-')[0]);
    } else {
        pickerYear.value = new Date().getFullYear();
    }
    isPickerOpen.value = !isPickerOpen.value;
};

const selectMonth = (monthNum) => {
    const formattedMonth = String(monthNum).padStart(2, '0');
    filterMonth.value = `${pickerYear.value}-${formattedMonth}`;
    isPickerOpen.value = false;
};

const displayPeriod = computed(() => {
    if (!filterMonth.value) return 'All Time';
    const parts = filterMonth.value.split('-');
    if (parts.length < 2) return 'All Time';
    const y = parts[0];
    const m = parseInt(parts[1]);
    if (isNaN(m) || m < 1 || m > 12) return 'All Time';
    return `${monthNames[m - 1]} ${y}`;
});
// ---------------------------------------

// Modal & Negotiation logic
const showModal = ref(false);
const selectedOrder = ref(null);
const loadingDetails = ref(false);
const activeNegotiateTab = ref('accept');

const canBargain = computed(() => {
    if (!selectedOrder.value || !selectedOrder.value.company) return false;
    const company = selectedOrder.value.company;
    
    // Do not override punchout: if punchout is enabled or order is punchout, bargaining is disabled
    if (company.punchout_enabled || selectedOrder.value.punchout_po_reference) {
        return false;
    }
    
    // Bargaining is an optional setting in each client (defaults to true if not strictly set to false)
    return company.bargaining_enabled !== false;
});
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

const formatOnInput = (idx, val) => {
    if (val === null || val === undefined || val === '') {
        bargainForm.items[idx].buyer_requested_price = '';
        return;
    }
    let clean = val.replace(/[^0-9.]/g, '');
    const dotCount = (clean.match(/\./g) || []).length;
    if (dotCount > 1) {
        const firstDotIdx = clean.indexOf('.');
        clean = clean.substring(0, firstDotIdx + 1) + clean.substring(firstDotIdx + 1).replace(/\./g, '');
    }
    const parts = clean.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    bargainForm.items[idx].buyer_requested_price = parts.join('.');
};

const handleBlur = (idx) => {
    const val = bargainForm.items[idx].buyer_requested_price;
    bargainForm.items[idx].buyer_requested_price = formatNumberWithCommas(val);
};

const calculateBargainTotal = () => {
    if (!selectedOrder.value || !bargainForm.items.length) return 0;
    return selectedOrder.value.items.reduce((sum, item, idx) => {
        const requested = bargainForm.items[idx]?.buyer_requested_price;
        const cleanVal = parseNumberFromCommas(requested);
        const qty = item.quantity || 0;
        return sum + (Number(cleanVal) || 0) * qty;
    }, 0);
};

const getBargainWarning = (idx) => {
    const item = bargainForm.items[idx];
    if (!item) return null;
    
    const cleanVal = parseNumberFromCommas(item.buyer_requested_price);
    const requested = parseFloat(cleanVal);
    if (cleanVal === '' || isNaN(requested) || requested <= 0) {
        return {
            type: 'error',
            message: 'Bargain price cannot be empty or 0'
        };
    }

    const offered = parseFloat(item.offered_price);
    if (requested > offered) {
        return {
            type: 'error',
            message: `Cannot exceed offered price of ${formatCurrency(offered)}`
        };
    }

    const tolerance = parseFloat(item.tolerance_percentage || 0);
    if (tolerance > 0) {
        const minPrice = offered * (1 - tolerance / 100);
        if (requested < minPrice) {
            return {
                type: 'warning',
                message: 'We are sorry we cannot accept the price'
            };
        }
    }
    return null;
};

const hasBargainErrors = () => {
    if (!bargainForm.items.length) return false;
    for (let i = 0; i < bargainForm.items.length; i++) {
        const warning = getBargainWarning(i);
        if (warning) return true;
    }
    return false;
};

const bargainForm = useForm({
    items: [],
    comment: '',
});

const acceptForm = useForm({
    po_attachment: null,
});

const openDetails = async (orderId) => {
    showModal.value = true;
    loadingDetails.value = true;
    try {
        const res = await axios.get(route('orders.show', orderId));
        selectedOrder.value = res.data;

        if (selectedOrder.value && ['Approved', 'Quotation'].includes(selectedOrder.value.status)) {
            activeNegotiateTab.value = 'accept';
            bargainForm.items = selectedOrder.value.items.map(item => {
                const initialPrice = item.buyer_requested_price !== null ? item.buyer_requested_price : (item.supplier_offered_price !== null ? item.supplier_offered_price : item.price);
                const offeredPrice = item.supplier_offered_price !== null ? item.supplier_offered_price : item.price;
                const tolerance = item.product?.tolerance_percentage !== null && item.product?.tolerance_percentage !== undefined ? parseFloat(item.product.tolerance_percentage) : 0;
                return {
                    id: item.id,
                    buyer_requested_price: formatNumberWithCommas(initialPrice),
                    offered_price: offeredPrice,
                    tolerance_percentage: tolerance
                };
            });
            bargainForm.comment = '';
        }
    } catch (e) {
        console.error(e);
    } finally {
        loadingDetails.value = false;
    }
};

const closeModal = () => {
    showModal.value = false;
    
    // Clean up URL parameters to prevent persistent URL leakage on refresh/back/forward
    if (typeof window !== 'undefined') {
        const url = new URL(window.location);
        url.searchParams.delete('open_order');
        url.searchParams.delete('page');
        window.history.replaceState({}, '', url);
    }

    setTimeout(() => { selectedOrder.value = null; }, 300);
};

const submitBargain = (orderId) => {
    const originalItems = JSON.parse(JSON.stringify(bargainForm.items));
    bargainForm.items = bargainForm.items.map(item => {
        const cleanPrice = parseNumberFromCommas(item.buyer_requested_price);
        return {
            id: item.id,
            buyer_requested_price: cleanPrice !== '' ? parseFloat(cleanPrice) : null
        };
    });

    bargainForm.post(route('orders.negotiation.bargain', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            openDetails(orderId);
            bargainForm.reset('comment');
        },
        onError: () => {
            bargainForm.items = originalItems;
        }
    });
};

const poPreviewUrl = ref(null);

const handlePoUpload = (event) => {
    const file = event.target.files[0];
    acceptForm.po_attachment = file;
    // Revoke previous URL to avoid memory leaks
    if (poPreviewUrl.value) URL.revokeObjectURL(poPreviewUrl.value);
    poPreviewUrl.value = file ? URL.createObjectURL(file) : null;
};

const submitAccept = (orderId) => {
    acceptForm.post(route('orders.negotiation.accept', orderId), {
        preserveScroll: true,
        onSuccess: () => {
            openDetails(orderId);
            acceptForm.reset('po_attachment');
            if (poPreviewUrl.value) { URL.revokeObjectURL(poPreviewUrl.value); poPreviewUrl.value = null; }
        },
    });
};

const checkOpenOrderParameter = () => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams(window.location.search);
    const openOrderId = params.get('open_order');
    if (openOrderId) {
        openDetails(openOrderId);
    }
};

onMounted(() => {
    checkOpenOrderParameter();
});

watch(() => page.url, () => {
    checkOpenOrderParameter();
});

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'RFQ': return 'bg-amber-50 text-amber-700 border-amber-100';
        case 'Submitted': return 'bg-blue-50 text-blue-700 border-blue-100';
        case 'Approved': return 'bg-teal-50 text-teal-700 border-teal-100';
        case 'Quotation': return 'bg-indigo-50 text-indigo-700 border-indigo-100';
        case 'PO': return 'bg-violet-50 text-violet-700 border-violet-100';
        case 'Invoiced': return 'bg-cyan-50 text-cyan-700 border-cyan-100';
        case 'Partially Shipped': return 'bg-orange-50 text-orange-700 border-orange-100';
        case 'Shipped': return 'bg-orange-50 text-orange-700 border-orange-100';
        case 'Completed': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'Rejected': return 'bg-red-50 text-red-700 border-red-100';
        default: return 'bg-gray-50 text-gray-700 border-gray-100';
    }
};

const getTrackingUrl = (order) => {
    if (!order || !order.tracking_number) return null;
    if (!order.carrier || !order.carrier.tracking_url_pattern) return null;
    return order.carrier.tracking_url_pattern.replace('{tracking_number}', encodeURIComponent(order.tracking_number));
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-extrabold text-2xl text-[#1a2b4c] leading-tight tracking-tight">Buyer Dashboard</h2>
                    <p class="text-sm text-gray-500 mt-1">View summary statistics and company transaction history.</p>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-6xl mx-auto space-y-8">
            
            <!-- Brighter Login Notice Alert (Flashed Only After Auth) -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 transform -translate-y-4"
                enter-to-class="opacity-100 transform translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showSuccessAlert" class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p v-if="successMessage.includes('logged in')" class="text-sm font-bold text-emerald-800">
                                Successful Log-in! <span class="font-medium text-emerald-700">Welcome back to your procurement dashboard.</span>
                            </p>
                            <p v-else class="text-sm font-bold text-emerald-800">
                                {{ successMessage }}
                            </p>
                        </div>
                    </div>
                    <button @click="dismissAlert" class="text-emerald-500 hover:text-emerald-700 transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </div>
            </Transition>

            <!-- Tasks To Do Widget -->
            <div v-if="totalTasksCount > 0" class="bg-gradient-to-r from-amber-50 to-amber-100/30 rounded-2xl border border-amber-200/60 p-6 shadow-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <div class="bg-amber-500 text-white p-1.5 rounded-lg shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-extrabold text-[#1a2b4c]">Tasks To Do</h3>
                    <span class="bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full text-xs font-bold">{{ totalTasksCount }} Action Required</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- 1. Pending Approvals -->
                    <div 
                        v-for="item in pendingApprovals" 
                        :key="'appr-' + item.id"
                        class="bg-white rounded-xl p-4 border border-amber-100 shadow-sm hover:shadow-md hover:border-amber-300 transition duration-200 flex flex-col justify-between cursor-pointer relative group"
                        @click="openDetails(item.id)"
                    >
                        <div>
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-100 uppercase tracking-wide">Approval Signature</span>
                                <span class="text-[10px] text-gray-400">{{ item.created_at }}</span>
                            </div>
                            <p class="text-sm font-bold text-[#1a2b4c] mt-2 truncate">{{ item.company_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Order #{{ item.id }} needs your workflow signature to advance.</p>
                        </div>
                        <div class="mt-4 flex items-center justify-between border-t border-gray-50 pt-3">
                            <span class="text-sm font-extrabold text-[#1a2b4c]">{{ formatCurrency(item.total) }}</span>
                            <button 
                                type="button"
                                class="text-xs font-bold text-white bg-[#e96a25] hover:bg-[#d0591b] px-3 py-1.5 rounded-lg shadow-sm transition-colors flex items-center group-hover:scale-105 transform"
                            >
                                Review Order
                                <svg class="w-3 h-3 ml-1 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Pending RFQs to submit (only rendered if exists, e.g. buyer acts as multiple roles) -->
                    <div 
                        v-for="item in pendingRfqs" 
                        :key="'rfq-' + item.id"
                        class="bg-white rounded-xl p-4 border border-indigo-100 shadow-sm hover:shadow-md hover:border-indigo-300 transition duration-200 flex flex-col justify-between cursor-pointer relative group"
                        @click="openDetails(item.id)"
                    >
                        <div>
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100 uppercase tracking-wide">RFQ Submission</span>
                                <span class="text-[10px] text-gray-400">{{ item.created_at }}</span>
                            </div>
                            <p class="text-sm font-bold text-[#1a2b4c] mt-2 truncate">{{ item.company_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Order #{{ item.id }} sits in RFQ status and must be submitted.</p>
                        </div>
                        <div class="mt-4 flex items-center justify-between border-t border-gray-50 pt-3">
                            <span class="text-sm font-extrabold text-[#1a2b4c]">{{ formatCurrency(item.total) }}</span>
                            <button 
                                type="button"
                                class="text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-lg shadow-sm transition-colors flex items-center group-hover:scale-105 transform"
                            >
                                Submit RFQ
                                <svg class="w-3 h-3 ml-1 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Filter Bar (Month only for global dashboard logic) -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h3 class="text-lg font-black text-[#1a2b4c]">Overview Statistics</h3>
                <!-- Premium Custom Month Picker -->
                <div class="relative">
                    <!-- The Capsule Button -->
                    <button 
                        @click="openPicker"
                        type="button"
                        class="flex items-center space-x-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-sm hover:border-[#e96a25] transition-all min-w-[160px] text-left group"
                    >
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100 pr-2 shrink-0 group-hover:text-[#e96a25]">Period</span>
                        <span class="text-xs font-bold text-[#1a2b4c] flex-1">{{ displayPeriod }}</span>
                        <!-- Simple Chevron Icon -->
                        <svg class="w-3 h-3 text-gray-400 group-hover:text-[#e96a25] transition-transform" :class="{'rotate-180': isPickerOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <!-- Transparent Click-Outside Overlay -->
                    <div v-if="isPickerOpen" @click="isPickerOpen = false" class="fixed inset-0 z-40"></div>

                    <!-- The Floating Premium Picker Card -->
                    <div v-if="isPickerOpen" class="absolute right-0 mt-2 w-64 bg-white border border-gray-100 rounded-2xl shadow-2xl z-50 p-4 origin-top-right transform transition-all duration-200 ease-out scale-100 opacity-100">
                         <!-- Header: Year Nav -->
                         <div class="flex items-center justify-between mb-4 border-b border-gray-50 pb-2">
                             <button type="button" @click.stop="pickerYear--" class="p-1.5 hover:bg-gray-100 rounded-full text-gray-500 hover:text-[#e96a25] transition-colors">
                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                             </button>
                             <span class="text-sm font-black text-[#1a2b4c] tracking-wide">{{ pickerYear }}</span>
                             <button type="button" @click.stop="pickerYear++" class="p-1.5 hover:bg-gray-100 rounded-full text-gray-500 hover:text-[#e96a25] transition-colors">
                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                             </button>
                         </div>
                         <!-- Grid: Months -->
                         <div class="grid grid-cols-3 gap-2">
                             <button 
                                v-for="(m, i) in monthNames" 
                                :key="i"
                                type="button"
                                @click.stop="selectMonth(i+1)"
                                :class="[
                                    'py-2 text-xs font-bold rounded-lg transition-all border',
                                    filterMonth === `${pickerYear}-${String(i+1).padStart(2,'0')}` 
                                       ? 'bg-[#e96a25] text-white border-[#e96a25] shadow-md shadow-orange-200'
                                       : 'text-gray-600 bg-white border-transparent hover:border-orange-100 hover:bg-orange-50 hover:text-[#e96a25]'
                                ]"
                             >{{ m }}</button>
                         </div>
                         
                         <!-- Clear Option -->
                         <div class="mt-3 pt-2 border-t border-gray-50 text-center">
                             <button 
                                type="button" 
                                @click.stop="filterMonth = ''; isPickerOpen = false;"
                                class="text-[10px] font-bold text-gray-400 hover:text-[#e96a25] uppercase tracking-wider"
                             >
                                 Clear Filter
                             </button>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Pending Card -->
                <div 
                    @click="toggleStatusFilter('RFQ')"
                    :class="[
                        'p-5 rounded-2xl border relative overflow-hidden group cursor-pointer transition-all duration-300 active:scale-[0.98]',
                        filterStatus === 'RFQ' 
                            ? 'bg-amber-50/60 border-amber-400 ring-1 ring-amber-400 shadow-md shadow-amber-50' 
                            : 'bg-white border-gray-100 shadow-sm hover:border-amber-300 hover:shadow-md hover:shadow-amber-50/50'
                    ]"
                >
                    <div class="absolute top-0 right-0 p-5 text-amber-400 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400">RFQ</p>
                    <h4 class="text-4xl font-black text-[#1a2b4c] mt-2">{{ summary?.RFQ || 0 }}</h4>
                    <div class="mt-3 w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-amber-400 h-1.5 rounded-full transition-all duration-500" :style="{ width: filterStatus === 'RFQ' ? '100%' : '25%' }"></div>
                    </div>
                </div>

                <!-- Submitted Card -->
                <div 
                    @click="toggleStatusFilter('Quotation')"
                    :class="[
                        'p-5 rounded-2xl border relative overflow-hidden group cursor-pointer transition-all duration-300 active:scale-[0.98]',
                        filterStatus === 'Quotation' 
                            ? 'bg-indigo-50/60 border-indigo-500 ring-1 ring-indigo-500 shadow-md shadow-indigo-50' 
                            : 'bg-white border-gray-100 shadow-sm hover:border-indigo-300 hover:shadow-md hover:shadow-indigo-50/50'
                    ]"
                >
                    <div class="absolute top-0 right-0 p-5 text-indigo-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Quotation</p>
                    <h4 class="text-4xl font-black text-[#1a2b4c] mt-2">{{ summary?.Quotation || 0 }}</h4>
                    <div class="mt-3 w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-500" :style="{ width: filterStatus === 'Quotation' ? '100%' : '25%' }"></div>
                    </div>
                </div>

                <!-- Approved Card -->
                <div 
                    @click="toggleStatusFilter('PO')"
                    :class="[
                        'p-5 rounded-2xl border relative overflow-hidden group cursor-pointer transition-all duration-300 active:scale-[0.98]',
                        filterStatus === 'PO' 
                            ? 'bg-violet-50/60 border-violet-500 ring-1 ring-violet-500 shadow-md shadow-violet-50' 
                            : 'bg-white border-gray-100 shadow-sm hover:border-violet-300 hover:shadow-md hover:shadow-violet-50/50'
                    ]"
                >
                    <div class="absolute top-0 right-0 p-5 text-violet-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Purchase Order</p>
                    <h4 class="text-4xl font-black text-[#1a2b4c] mt-2">{{ summary?.PO || 0 }}</h4>
                    <div class="mt-3 w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-violet-500 h-1.5 rounded-full transition-all duration-500" :style="{ width: filterStatus === 'PO' ? '100%' : '25%' }"></div>
                    </div>
                </div>

                <!-- Finished Card -->
                <div 
                    @click="toggleStatusFilter('Completed')"
                    :class="[
                        'p-5 rounded-2xl border relative overflow-hidden group cursor-pointer transition-all duration-300 active:scale-[0.98]',
                        filterStatus === 'Completed' 
                            ? 'bg-emerald-50/60 border-emerald-500 ring-1 ring-emerald-500 shadow-md shadow-emerald-50' 
                            : 'bg-white border-gray-100 shadow-sm hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-50/50'
                    ]"
                >
                    <div class="absolute top-0 right-0 p-5 text-emerald-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Completed</p>
                    <h4 class="text-4xl font-black text-[#1a2b4c] mt-2">{{ summary?.Completed || 0 }}</h4>
                    <div class="mt-3 w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" :style="{ width: filterStatus === 'Completed' ? '100%' : '25%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Divider with Status Sub-Filter for Grid -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <h3 class="text-lg font-black text-[#1a2b4c]">Recent Transactions</h3>
                <div class="flex items-center gap-3">
                    <select v-model="filterStatus" class="border-gray-200 rounded-lg text-xs font-bold focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm">
                        <option value="">Filter History by Status</option>
                        <option v-for="st in statuses" :key="st" :value="st">{{ st }}</option>
                    </select>
                    <button v-if="filterStatus || filterMonth" @click="resetFilters" class="text-xs font-bold text-gray-400 hover:text-[#e96a25] transition">Clear</button>
                </div>
            </div>

            <!-- Order History Grid Copy (formerly orders index) -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-50">
                        <thead class="bg-gray-50/50 text-left">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Order Ref</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Date Placed</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-right">Total</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-center">Status</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 bg-white">
                            <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50/50 transition-colors duration-150">
                                <td class="px-6 py-5">
                                    <button @click="openDetails(order.id)" class="font-black text-gray-900 hover:text-[#e96a25] transition-colors border-b border-dashed border-gray-300 hover:border-[#e96a25] text-left text-sm">
                                        #{{ String(order.id).padStart(6, '0') }}
                                    </button>
                                </td>
                                <td class="px-6 py-5 text-sm font-medium text-gray-600">
                                    {{ new Date(order.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                                </td>
                                <td class="px-6 py-5 text-right font-black text-[#1a2b4c]">
                                    {{ formatCurrency(order.total) }}
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span :class="['px-3 py-1 rounded-full text-xs font-extrabold border', getStatusBadgeClass(order.status)]">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <button @click="openDetails(order.id)" 
                                        class="inline-flex items-center space-x-1 font-bold text-[#e96a25] hover:text-[#b84a14] text-sm transition">
                                        <span>Details</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="orders.data.length === 0">
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center text-gray-400">
                                        <svg class="w-12 h-12 mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                        <p class="font-bold">No matching transactions.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 bg-gray-50/30 border-t border-gray-50">
                    <Pagination :links="orders.links" />
                </div>
            </div>
        </div>

        <!-- View Modal for User -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div @click="closeModal" class="fixed inset-0 bg-[#1a2b4c]/50 backdrop-blur-sm transition-opacity"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full border border-gray-100">
                    
                    <div v-if="loadingDetails" class="p-16 flex flex-col items-center justify-center">
                        <div class="w-10 h-10 border-4 border-[#e96a25]/20 border-t-[#e96a25] rounded-full animate-spin"></div>
                    </div>

                    <div v-else-if="selectedOrder" class="p-6 space-y-6">
                        <div class="flex justify-between items-start border-b border-dashed border-gray-200 pb-4">
                            <div>
                                <h2 class="text-2xl font-black text-[#1a2b4c]">Order Receipt</h2>
                                <p class="text-sm text-gray-500 mt-0.5">Ref #{{ String(selectedOrder.id).padStart(6, '0') }}</p>
                            </div>
                            <span :class="['px-3 py-1 border rounded-full text-xs font-extrabold', getStatusBadgeClass(selectedOrder.status)]">
                                {{ selectedOrder.status }}
                            </span>
                        </div>

                        <!-- Display rejection reason if present -->
                        <div v-if="selectedOrder.status === 'Rejected' && selectedOrder.rejection_reason" class="bg-red-50 border border-red-100 rounded-xl p-4 text-sm text-red-800">
                            <div class="font-bold text-xs uppercase text-red-600 mb-1">Reason for Rejection</div>
                            "{{ selectedOrder.rejection_reason }}"
                        </div>

                        <!-- cXML Integration & B2B Details -->
                        <div v-if="selectedOrder.punchout_po_reference" class="bg-[#1a2b4c]/5 border border-[#1a2b4c]/10 rounded-xl p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-[#1a2b4c]/10 pb-2">
                                <h4 class="font-extrabold text-xs text-[#1a2b4c] uppercase tracking-wider flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    B2B Integration Info
                                </h4>
                                <span v-if="selectedOrder.deployment_mode" :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase border', selectedOrder.deployment_mode === 'test' ? 'bg-amber-100 border-amber-200 text-amber-800' : 'bg-green-100 border-green-200 text-green-800']">
                                    {{ selectedOrder.deployment_mode }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-[10px] font-extrabold text-gray-400 block uppercase tracking-wider">ERP PO Ref</span>
                                    <span class="font-black font-mono text-[#1a2b4c] text-xs">{{ selectedOrder.punchout_po_reference }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-extrabold text-gray-400 block uppercase tracking-wider">Payload Date</span>
                                    <span class="font-semibold text-gray-600 text-xs">{{ selectedOrder.po_date || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-extrabold text-gray-400 block uppercase tracking-wider">Settlement CCY</span>
                                    <span class="font-black text-gray-800 text-xs">{{ selectedOrder.currency || 'USD' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- PO Attachment Link Section -->
                        <div v-if="selectedOrder.po_attachment" class="bg-violet-50/50 border border-violet-100 rounded-xl p-3.5 mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 bg-violet-100 rounded-lg flex items-center justify-center text-violet-600 flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="truncate">
                                    <div class="text-xs font-black text-[#1a2b4c] uppercase tracking-wider">PO Attachment</div>
                                    <div class="text-[11px] text-violet-700 font-medium truncate max-w-[250px]">{{ selectedOrder.po_attachment.split('/').pop() }}</div>
                                </div>
                            </div>
                            <a 
                                :href="selectedOrder.po_attachment" 
                                target="_blank"
                                class="px-3.5 py-2 bg-white border border-violet-200 text-violet-600 text-xs font-bold rounded-lg hover:bg-violet-50 transition duration-200 shadow-sm hover:shadow-md flex items-center gap-1.5 flex-shrink-0"
                            >
                                <span>View</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        </div>

                        <!-- Clickable Thumbnail Preview for Photo PO -->
                        <div v-if="selectedOrder.po_attachment && isImageAttachment(selectedOrder.po_attachment)" class="mb-6">
                            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Photo Preview</div>
                            <a :href="selectedOrder.po_attachment" target="_blank" class="block group relative border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all max-w-xs">
                                <img :src="selectedOrder.po_attachment" class="w-full h-auto max-h-48 object-cover hover:scale-105 transition-transform duration-300" alt="PO Attachment Image" />
                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    View Full Image
                                </div>
                            </a>
                        </div>
                        <!-- Shipments & Delivery Tracking -->
                        <div v-if="selectedOrder.shipments && selectedOrder.shipments.length > 0" class="mb-6">
                            <h4 class="font-bold text-xs text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Shipments & Delivery Tracking
                            </h4>
                            <div class="bg-orange-50/20 rounded-2xl border border-orange-100/50 p-5 space-y-4">
                                <div class="flow-root">
                                    <ul role="list" class="-mb-8">
                                        <li v-for="(shipment, shipIdx) in selectedOrder.shipments" :key="shipment.id">
                                            <div class="relative pb-8">
                                                <span v-if="shipIdx !== selectedOrder.shipments.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-orange-100" aria-hidden="true"></span>
                                                
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white bg-orange-50 border border-orange-100 text-orange-600 shadow-sm">
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
                                                                <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-white border border-gray-150 rounded text-gray-700 shadow-xs">
                                                                    Waybill: {{ shipment.tracking_number }}
                                                                </span>
                                                                <a v-if="shipment.carrier?.tracking_url_pattern || shipment.carrier?.tracking_url" 
                                                                   :href="(shipment.carrier.tracking_url_pattern || shipment.carrier.tracking_url).replace('{tracking_number}', shipment.tracking_number)" 
                                                                   target="_blank" 
                                                                   class="text-[10px] font-bold text-[#e96a25] hover:underline flex items-center gap-0.5">
                                                                    <span>Track Package</span>
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                                                </a>
                                                                <a :href="'/storage/' + shipment.delivery_note_path" 
                                                                   target="_blank" 
                                                                   class="text-[10px] font-bold text-indigo-600 hover:underline flex items-center gap-0.5">
                                                                    <span>Delivery Note (PDF)</span>
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                                </a>
                                                            </div>
                                                            <!-- Display shipped items -->
                                                            <div class="text-[11px] text-gray-650 bg-white border border-gray-150 rounded-xl p-3 space-y-1 shadow-xs font-semibold max-w-md">
                                                                <div class="font-bold text-gray-400 uppercase text-[9px] tracking-wider mb-1">Shipped Items</div>
                                                                <div v-for="si in shipment.items" :key="si.id" class="flex justify-between">
                                                                    <span>{{ si.order_item?.product?.name || 'Item' }}</span>
                                                                    <span class="font-bold text-gray-700">Qty: {{ si.quantity }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-right text-[10px] whitespace-nowrap text-gray-400 font-semibold">
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

                        <!-- Invoice & Documentation -->
                        <div v-if="selectedOrder.invoice_attachment || (selectedOrder.invoice_documents && selectedOrder.invoice_documents.length > 0)" class="mb-6">
                            <h4 class="font-bold text-xs text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Invoice & Commercial Documents
                            </h4>
                            <div class="bg-emerald-50/20 rounded-2xl border border-emerald-100/50 p-5 space-y-3">
                                <div v-if="selectedOrder.invoice_attachment" class="flex items-center justify-between bg-white border border-gray-150 p-3 rounded-xl shadow-xs">
                                    <div class="flex items-center space-x-2">
                                        <svg class="w-8 h-8 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A1 1 0 0112 2.586L15.414 6A1 1 0 0116 6.707V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 012 0h4a1 1 0 110 2H8a1 1 0 01-2-1z" clip-rule="evenodd"/></svg>
                                        <div>
                                            <div class="text-xs font-bold text-gray-800">Commercial Invoice</div>
                                            <div class="text-[10px] text-gray-400 font-mono">Main Financial Invoice</div>
                                        </div>
                                    </div>
                                    <a :href="`/storage/${selectedOrder.invoice_attachment}`" target="_blank" class="px-3.5 py-1.5 bg-[#e96a25] hover:bg-[#d0591b] text-white text-xs font-bold rounded-xl transition flex items-center space-x-1 shadow-sm hover:shadow">
                                        <span>Download</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                </div>
                                <div v-if="selectedOrder.invoice_documents && selectedOrder.invoice_documents.length > 0" class="space-y-2">
                                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Supporting Documentation</div>
                                    <div v-for="doc in selectedOrder.invoice_documents" :key="doc.path" class="flex items-center justify-between bg-white border border-gray-150 p-3 rounded-xl shadow-xs">
                                        <div class="flex items-center space-x-2">
                                            <svg class="w-7 h-7 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A1 1 0 0016.586 6L14 3.414A1 1 0 0013.293 3H9z"/><path d="M5 6a1 1 0 00-1 1v8a3 3 0 003 3h7a1 1 0 100-2H7a1 1 0 01-1-1V7a1 1 0 00-1-1z"/></svg>
                                            <div class="text-xs font-bold text-gray-800 truncate max-w-xs">{{ doc.name }}</div>
                                        </div>
                                        <a :href="`/storage/${doc.path}`" target="_blank" class="px-3 py-1.5 bg-gray-55 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-xl transition flex items-center space-x-1 border border-gray-200">
                                            <span>Download</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address Blocks -->
                        <div v-if="selectedOrder.shipping_address || selectedOrder.billing_address" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Shipping Address -->
                            <div v-if="selectedOrder.shipping_address" class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2.5 flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    Delivery Address
                                </h4>
                                <div class="text-xs text-gray-800 font-black" v-if="selectedOrder.shipping_name">{{ selectedOrder.shipping_name }}</div>
                                <div class="text-[10px] text-gray-500 font-medium mb-1.5" v-if="selectedOrder.shipping_email">{{ selectedOrder.shipping_email }}</div>
                                <div class="text-xs text-gray-600 whitespace-pre-line leading-relaxed font-semibold bg-white border border-gray-100 p-2 rounded-lg">{{ selectedOrder.shipping_address }}</div>
                            </div>
                            <!-- Billing Address -->
                            <div v-if="selectedOrder.billing_address" class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2.5 flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    Billing Address
                                </h4>
                                <div class="text-xs text-gray-800 font-black" v-if="selectedOrder.billing_name">{{ selectedOrder.billing_name }}</div>
                                <div class="text-[10px] text-gray-500 font-medium mb-1.5" v-if="selectedOrder.billing_email">{{ selectedOrder.billing_email }}</div>
                                <div class="text-xs text-gray-600 whitespace-pre-line leading-relaxed font-semibold bg-white border border-gray-100 p-2 rounded-lg">{{ selectedOrder.billing_address }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-bold text-gray-400 uppercase mb-3 flex justify-between items-center border-b border-gray-50 pb-2">
                                <span>Items Breakdown</span>
                                <span class="normal-case font-medium">{{ new Date(selectedOrder.created_at).toLocaleDateString() }}</span>
                            </div>

                            <ul class="divide-y divide-gray-50 border border-gray-50 rounded-xl overflow-hidden bg-gray-50/30">
                                <li v-for="item in selectedOrder.items" :key="item.id" class="flex justify-between text-sm p-3 hover:bg-white transition duration-150">
                                    <div class="flex-1 pr-4">
                                        <template v-if="item.product">
                                            <Link :href="route('catalog.show', { product: item.product.id, origin: 'orders', order_id: selectedOrder.id })" class="font-bold text-gray-800 hover:text-[#e96a25] hover:underline transition">{{ item.product.name }}</Link>
                                            <div class="text-[11px] text-gray-400 font-medium mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 items-center">
                                                <span class="font-mono">SKU: {{ item.product.sku }}</span>
                                                <span v-if="item.product.uom" class="text-[#e96a25] font-black">• UOM: {{ item.product.uom }}</span>
                                                <span v-if="item.product.classification" class="text-indigo-600 italic">• UN: {{ item.product.classification }}</span>
                                            </div>
                                            <div v-if="item.product.manufacturer_part_id || item.product.manufacturer_name" class="text-[9px] text-gray-400 uppercase font-semibold tracking-wider mt-0.5">
                                                MFG: {{ item.product.manufacturer_name || '-' }} [{{ item.product.manufacturer_part_id || '-' }}]
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="font-bold text-gray-400">Deleted product</div>
                                        </template>
                                        <div class="text-[10px] font-semibold text-gray-500 mt-1 bg-white inline-flex flex-wrap items-center gap-1.5 px-1.5 py-0.5 rounded border border-gray-100">
                                            <span>Qty: {{ item.quantity }}</span>
                                            <span class="text-gray-300">•</span>
                                            <span class="font-bold text-gray-700">{{ formatCurrency(item.supplier_offered_price !== null ? item.supplier_offered_price : item.price) }}</span>
                                            <span v-if="item.supplier_offered_price !== null && Number(item.price) !== Number(item.supplier_offered_price)" class="text-[9px] text-gray-400 line-through decoration-red-400/40">
                                                ({{ formatCurrency(item.price) }})
                                            </span>
                                        </div>
                                    </div>
                                    <div class="font-black text-[#1a2b4c] whitespace-nowrap text-right flex items-center">
                                        {{ formatCurrency((item.supplier_offered_price !== null ? item.supplier_offered_price : item.price) * item.quantity) }}
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="bg-gray-50/50 border border-gray-100 rounded-xl p-4 space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 font-semibold">Subtotal</span>
                                <span class="text-gray-700 font-bold">{{ formatCurrency(selectedOrder.total) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-t border-dashed border-gray-200 pt-3">
                                <span class="text-[#1a2b4c] font-black text-base">Grand Total</span>
                                <span class="text-xl font-black text-[#e96a25]">{{ formatCurrency(selectedOrder.total) }}</span>
                            </div>
                        </div>

                        <!-- Approval Trail Logs Section -->
                        <div v-if="$page.props.auth.roles.some(r => ['admin', 'supplier_admin', 'supplier_processor', 'supplier_approver'].includes(r)) && (selectedOrder.approval_logs && selectedOrder.approval_logs.length > 0)" class="mt-6 border-t border-gray-100 pt-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Approval Trail Log</h4>
                            <div class="space-y-3 overflow-y-auto max-h-48 pr-1">
                                <div v-for="log in selectedOrder.approval_logs" :key="log.id" class="flex gap-3 text-sm">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold uppercase text-xs border border-indigo-100">
                                        {{ log.user?.name?.charAt(0) }}
                                    </div>
                                    <div class="flex-1 bg-gray-50 rounded-xl p-3 border border-gray-100">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="font-bold text-gray-900 text-xs">{{ log.user?.name }}</span>
                                            <span class="text-[10px] text-gray-400 font-medium">{{ new Date(log.created_at).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</span>
                                        </div>
                                        <div class="inline-block text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded border"
                                            :class="log.action.toLowerCase() === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : (log.action.toLowerCase() === 'rejected' ? 'bg-red-50 text-red-700 border-red-100' : 'bg-blue-50 text-blue-700 border-red-100')">
                                            {{ log.action }}
                                        </div>
                                        <p v-if="log.note" class="text-xs text-gray-600 italic mt-1.5 bg-white/50 p-1.5 rounded">"{{ log.note }}"</p>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <!-- Negotiation Panel (For Status = Approved or Quotation) -->
                        <div v-if="['Approved', 'Quotation'].includes(selectedOrder.status)" class="mt-8 border-t border-gray-100 pt-8 space-y-6">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <h4 class="font-black text-base text-[#1a2b4c] flex items-center gap-2 uppercase tracking-wider">
                                        <span class="p-1.5 rounded-lg bg-orange-50 border border-orange-100 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </span>
                                        Review Quotation & Actions
                                    </h4>
                                    <p v-if="canBargain" class="text-xs text-gray-500 font-medium">Select an action: either accept the supplier quotation or propose a bargain price.</p>
                                    <p v-else class="text-xs text-gray-500 font-medium">Please review the supplier quotation and accept to generate the Purchase Order.</p>
                                </div>

                                <!-- Premium Segmented Control Selector -->
                                <div v-if="canBargain" class="flex p-1 bg-gray-100/80 backdrop-blur-md rounded-xl border border-gray-200/50 self-start md:self-auto shadow-sm min-w-[340px]">
                                    <button 
                                        type="button"
                                        @click="activeNegotiateTab = 'accept'" 
                                        :class="activeNegotiateTab === 'accept' 
                                            ? 'bg-white text-emerald-955 shadow-md border-emerald-100/50 font-bold scale-[1.02]' 
                                            : 'text-gray-500 hover:text-gray-800 font-semibold'"
                                        class="flex-1 py-2.5 px-4 text-xs rounded-lg transition-all duration-200 flex items-center justify-center gap-2 border border-transparent"
                                    >
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Accept & Approve
                                    </button>
                                    <button 
                                        type="button"
                                        @click="activeNegotiateTab = 'bargain'" 
                                        :class="activeNegotiateTab === 'bargain' 
                                            ? 'bg-white text-amber-955 shadow-md border-amber-100/50 font-bold scale-[1.02]' 
                                            : 'text-gray-500 hover:text-gray-800 font-semibold'"
                                        class="flex-1 py-2.5 px-4 text-xs rounded-lg transition-all duration-200 flex items-center justify-center gap-2 border border-transparent"
                                    >
                                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                        Bargain & Negotiate
                                    </button>
                                </div>
                            </div>

                            <!-- Option A: Bargain Price Panel -->
                            <div v-if="activeNegotiateTab === 'bargain'" class="bg-amber-50/20 border border-amber-100/80 rounded-2xl p-6 shadow-sm shadow-amber-50 animate-fadeIn">
                                <div class="mb-5 flex gap-3.5 items-start">
                                    <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 text-sm font-extrabold flex-shrink-0">B</div>
                                    <div>
                                        <h5 class="text-xs font-extrabold text-amber-900 uppercase tracking-wider mb-1">Negotiate Pricing</h5>
                                        <p class="text-xs text-gray-500 font-medium leading-relaxed">
                                            Propose lower targeted unit prices for each item. The supplier will receive this bargain request and can either update their offer or bargain back.
                                        </p>
                                    </div>
                                </div>

                                <!-- Premium Items Table/List -->
                                <div class="border border-gray-100 rounded-xl overflow-hidden shadow-sm bg-white mb-6">
                                    <div class="grid grid-cols-12 bg-gray-50 border-b border-gray-100 px-4 py-3 text-[10px] font-black uppercase tracking-wider text-gray-400">
                                        <div class="col-span-5">Product Details</div>
                                        <div class="col-span-2 text-right">Offered Price</div>
                                        <div class="col-span-3 text-right">Bargain Price ({{ page.props.appSettings?.currency || 'EUR' }})</div>
                                        <div class="col-span-2 text-right">Line Total</div>
                                    </div>
                                    
                                    <div class="divide-y divide-gray-100">
                                        <div v-for="(item, idx) in selectedOrder.items" :key="item.id" class="grid grid-cols-12 items-start md:items-center px-4 py-4 md:py-3.5 hover:bg-gray-50/50 transition gap-y-3.5 md:gap-y-0">
                                            <!-- Col 1: Product Info -->
                                            <div class="col-span-12 md:col-span-5 flex flex-col pr-0 md:pr-4 w-full order-1">
                                                <span class="text-xs font-bold text-gray-800 truncate">{{ item.product?.name || 'Product' }}</span>
                                                <span class="text-[10px] text-gray-400 font-medium">Qty: {{ item.quantity || 0 }}</span>
                                                <div v-if="getBargainWarning(idx)" class="mt-1 flex items-center gap-1">
                                                    <span :class="[
                                                        'px-1.5 py-0.5 rounded text-[9px] font-bold leading-none',
                                                        getBargainWarning(idx).type === 'error' ? 'bg-red-50 text-red-500 border border-red-200' : 'bg-amber-50 text-amber-600 border border-amber-200'
                                                    ]">
                                                        {{ getBargainWarning(idx).message }}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Col 2: Offered Price -->
                                            <div class="col-span-6 md:col-span-2 text-left md:text-right flex flex-col items-start md:items-end justify-center order-2">
                                                <span class="text-[9px] md:hidden font-extrabold uppercase text-gray-400 tracking-wider mb-0.5">Offered Price</span>
                                                <span class="text-xs font-bold text-gray-850">{{ formatCurrency(item.supplier_offered_price !== null ? item.supplier_offered_price : item.price) }}</span>
                                                <span v-if="item.original_price !== null && Number(item.original_price) !== Number(item.supplier_offered_price || item.price)" class="text-[9px] text-gray-455 line-through decoration-red-400/40 font-bold mt-0.5">
                                                    Original: {{ formatCurrency(item.original_price) }}
                                                </span>
                                            </div>
                                            
                                            <!-- Col 3: Input Field -->
                                            <div class="col-span-12 md:col-span-3 flex flex-col md:flex-row md:items-center justify-start md:justify-end pl-0 md:pl-2 w-full order-4 md:order-3">
                                                 <span class="text-[9px] md:hidden font-extrabold uppercase text-gray-400 tracking-wider mb-1">Bargain Price ({{ page.props.appSettings?.currency || 'EUR' }})</span>
                                                 <input 
                                                     v-if="bargainForm.items[idx]"
                                                     type="text" 
                                                     :value="bargainForm.items[idx].buyer_requested_price"
                                                     @input="formatOnInput(idx, $event.target.value)"
                                                     @blur="handleBlur(idx)"
                                                     class="w-full md:w-32 text-right bg-gray-50/50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-extrabold text-gray-800 focus:bg-white focus:border-amber-500 focus:ring focus:ring-amber-200/40 transition shadow-inner"
                                                 />
                                             </div>

                                            <!-- Col 4: Line Total (Live calculation) -->
                                            <div class="col-span-6 md:col-span-2 text-right flex flex-col items-end justify-center order-3 md:order-4">
                                                <span class="text-[9px] md:hidden font-extrabold uppercase text-gray-400 tracking-wider mb-0.5">Line Total</span>
                                                <span class="text-xs font-black text-amber-700">
                                                    {{ formatCurrency(Number(parseNumberFromCommas(bargainForm.items[idx]?.buyer_requested_price || 0)) * (item.quantity || 0)) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Bargained Subtotal and Comment -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-2">Comment to Supplier (Optional)</label>
                                        <textarea 
                                            v-model="bargainForm.comment" 
                                            rows="3" 
                                            placeholder="Type your negotiation remarks here..."
                                            class="w-full text-xs border border-gray-200 rounded-2xl focus:border-amber-500 focus:ring focus:ring-amber-200/50 py-3 px-4 shadow-sm"
                                        ></textarea>
                                        <div v-if="bargainForm.errors.comment" class="text-red-500 text-[10px] mt-1">{{ bargainForm.errors.comment }}</div>
                                    </div>

                                    <!-- Bargain Summary Side Panel -->
                                    <div class="bg-amber-600/5 border border-amber-600/20 rounded-2xl p-4 flex flex-col justify-between">
                                        <div>
                                            <span class="text-[9px] font-extrabold uppercase text-amber-800 tracking-wider">Estimated Bargained Subtotal</span>
                                            <div class="text-xl font-black text-amber-800 mt-1 leading-none">
                                                {{ formatCurrency(calculateBargainTotal()) }}
                                            </div>
                                            <p class="text-[10px] text-gray-400 font-bold mt-2 leading-relaxed">
                                                Excluding taxes and shipping fees.
                                            </p>
                                        </div>
                                        
                                        <button 
                                            type="button"
                                            @click="submitBargain(selectedOrder.id)" 
                                            :disabled="bargainForm.processing || hasBargainErrors()"
                                            class="w-full mt-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition shadow-sm hover:shadow duration-200 flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span v-if="bargainForm.processing">Submitting...</span>
                                            <template v-else>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                                Send Bargain Request
                                            </template>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Option B: Accept & Upload PO Panel -->
                            <div v-if="activeNegotiateTab === 'accept'" class="bg-emerald-50/20 border border-emerald-100 rounded-2xl p-5 transition-all duration-300">
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 text-xs font-bold">A</span>
                                    <span class="text-xs font-black uppercase text-emerald-800 tracking-wider">Accept Quotation</span>
                                </div>
                                <p class="text-xs text-gray-500 font-medium mb-6 leading-relaxed">
                                    Ready to accept the quotation? Upload your formal Purchase Order document (PDF, PNG, JPG, up to 5MB) below to confirm your acceptance.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                                    <!-- Left PO Upload box -->
                                    <div class="md:col-span-2 bg-white border border-gray-150 border-dashed rounded-xl p-6 text-center shadow-sm relative group hover:border-emerald-300 transition duration-200">
                                        <input 
                                            type="file" 
                                            @change="handlePoUpload" 
                                            accept=".pdf,.png,.jpg,.jpeg" 
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        />
                                        <div class="space-y-2 py-4">
                                            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto group-hover:scale-110 transition duration-200">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                            </div>
                                            <div class="text-xs text-gray-600 font-bold mt-2">
                                                <span class="text-emerald-600 hover:text-emerald-700 underline">Upload PO document</span> or drag & drop
                                            </div>
                                            <div class="text-[10px] text-gray-400 font-medium">PDF, PNG, JPG up to 5MB</div>
                                        </div>
                                    </div>

                                    <!-- Right Confirmation summary card -->
                                    <div class="bg-emerald-600/5 border border-emerald-600/20 rounded-xl p-4 flex flex-col justify-between min-h-[160px]">
                                        <div>
                                            <span class="text-[9px] font-extrabold uppercase text-emerald-800 tracking-wider">Total Value to Approve</span>
                                            <div class="text-xl font-black text-emerald-800 mt-1 leading-none">
                                                {{ formatCurrency(selectedOrder.total) }}
                                            </div>
                                            
                                            <!-- Selected File indicator -->
                                            <div v-if="acceptForm.po_attachment" class="mt-4 bg-emerald-50 border border-emerald-100 rounded-lg px-2.5 py-1.5 flex items-center gap-2 text-[10px]">
                                                <svg class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                <span class="font-bold text-emerald-800 truncate flex-1 min-w-0">{{ acceptForm.po_attachment.name }}</span>
                                                <a 
                                                    v-if="poPreviewUrl" 
                                                    :href="poPreviewUrl" 
                                                    target="_blank" 
                                                    class="flex-shrink-0 flex items-center gap-1 text-emerald-700 font-black hover:text-emerald-900 underline underline-offset-2 transition"
                                                    title="Preview uploaded file"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    Preview
                                                </a>
                                            </div>
                                            <div v-if="acceptForm.errors.po_attachment" class="text-red-500 text-[10px] mt-2 font-bold">{{ acceptForm.errors.po_attachment }}</div>
                                        </div>
                                        
                                        <button 
                                            type="button"
                                            @click="submitAccept(selectedOrder.id)" 
                                            :disabled="acceptForm.processing || !acceptForm.po_attachment"
                                            class="w-full mt-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm hover:shadow duration-200 flex items-center justify-center gap-1.5 disabled:opacity-50"
                                        >
                                            <span v-if="acceptForm.processing">Uploading...</span>
                                            <template v-else>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                Accept & Approve Order
                                            </template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Negotiation & Comment History (Chronological Timeline) -->
                        <div v-if="selectedOrder.histories && selectedOrder.histories.length > 0" class="mt-6 border-t border-gray-100 pt-6">
                            <h4 class="font-bold text-xs text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                Negotiation & History Trail
                            </h4>
                            <div class="bg-indigo-50/20 rounded-xl border border-indigo-100/50 p-4 space-y-4">
                                <div class="flow-root">
                                    <ul role="list" class="-mb-8">
                                        <li v-for="(hist, histIdx) in selectedOrder.histories" :key="hist.id">
                                            <div class="relative pb-8">
                                                <span v-if="histIdx !== selectedOrder.histories.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-indigo-100" aria-hidden="true"></span>
                                                
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white bg-indigo-50 border border-indigo-100">
                                                            <svg class="h-3.5 h-3.5 text-indigo-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0 flex-1 flex justify-between space-x-4">
                                                        <div>
                                                            <p class="text-xs text-gray-500">
                                                                <span class="font-bold text-gray-900">{{ hist.user?.name || 'System' }}</span>
                                                                <span class="mx-1 text-gray-400">updated state:</span>
                                                                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider bg-gray-100 rounded text-gray-600">{{ hist.status_before }}</span>
                                                                <span class="mx-0.5 text-gray-400">→</span>
                                                                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider bg-[#1a2b4c]/5 rounded text-[#1a2b4c]">{{ hist.status_after }}</span>
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
                                                        <div class="text-right text-[10px] whitespace-nowrap text-gray-400 font-medium">
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

                        <div class="mt-8">
                            <button @click="closeModal" class="w-full py-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold transition duration-200 text-sm">
                                Back to List
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
