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

// Modal logic
const showModal = ref(false);
const selectedOrder = ref(null);
const loadingDetails = ref(false);

const openDetails = async (orderId) => {
    showModal.value = true;
    loadingDetails.value = true;
    try {
        const res = await axios.get(route('orders.show', orderId));
        selectedOrder.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingDetails.value = false;
    }
};

const fileInputError = ref('');
const selectedFileName = ref('');

const statusForm = useForm({
    status: '',
    po_attachment: null,
    _method: 'PATCH' // Required for file uploads to hit PATCH routes
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    // 5MB Limit = 5 * 1024 * 1024
    if (file.size > 5242880) {
        fileInputError.value = 'File exceeds 5MB threshold. Please select a smaller file.';
        selectedFileName.value = '';
        statusForm.po_attachment = null;
        return;
    }
    
    const validExtensions = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!validExtensions.includes(file.type)) {
        fileInputError.value = 'Unsupported file format. Please upload PDF or valid Image.';
        selectedFileName.value = '';
        statusForm.po_attachment = null;
        return;
    }
    
    fileInputError.value = '';
    selectedFileName.value = file.name;
    statusForm.po_attachment = file;
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => { 
        selectedOrder.value = null;
        statusForm.reset();
        selectedFileName.value = '';
        fileInputError.value = '';
    }, 300);
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

const isUpdating = ref(false);
const showStatusConfirmModal = ref(false);
const pendingStatus = ref('');

const updateOrderStatus = (statusVal) => {
    // Special requirement: require attachment before updating Quotation to PO
    if (statusVal === 'PO' && !statusForm.po_attachment) {
        fileInputError.value = 'You must attach a PDF or Image PO receipt to proceed.';
        return;
    }
    
    pendingStatus.value = statusVal;
    showStatusConfirmModal.value = true;
};

const executeStatusUpdate = () => {
    if (!selectedOrder.value || !pendingStatus.value) return;

    const targetUrl = route('orders.update-status', selectedOrder.value.id);
    
    statusForm.status = pendingStatus.value;
    
    // Use statusForm.post with method override since Inertia router.patch cannot reliably serialize binary files
    statusForm.post(targetUrl, {
        preserveScroll: true,
        onStart: () => { isUpdating.value = true; },
        onFinish: () => { 
            isUpdating.value = false; 
            showStatusConfirmModal.value = false; 
            pendingStatus.value = '';
        },
        onSuccess: () => {
            closeModal();
        },
        onError: (errors) => {
            console.error('Update Error:', errors);
        }
    });
};

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'RFQ': return 'bg-amber-50 text-amber-700 border-amber-100';
        case 'Submitted': return 'bg-blue-50 text-blue-700 border-blue-100';
        case 'Approved': return 'bg-teal-50 text-teal-700 border-teal-100';
        case 'Quotation': return 'bg-indigo-50 text-indigo-700 border-indigo-100';
        case 'PO': return 'bg-violet-50 text-violet-700 border-violet-100';
        case 'Invoiced': return 'bg-cyan-50 text-cyan-700 border-cyan-100';
        case 'Shipped': return 'bg-orange-50 text-orange-700 border-orange-100';
        case 'Completed': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'Rejected': return 'bg-red-50 text-red-700 border-red-100';
        default: return 'bg-gray-50 text-gray-700 border-gray-100';
    }
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

        <!-- In-Dash Detail Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div @click="closeModal" class="fixed inset-0 bg-[#1a2b4c]/50 backdrop-blur-sm transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-gray-100">
                    <div v-if="loadingDetails" class="p-16 flex flex-col items-center justify-center">
                        <div class="w-10 h-10 border-4 border-[#e96a25]/20 border-t-[#e96a25] rounded-full animate-spin"></div>
                    </div>
                    <div v-else-if="selectedOrder" class="p-6">
                        <div class="flex justify-between items-start border-b border-dashed border-gray-200 pb-4 mb-4">
                            <div>
                                <h2 class="text-2xl font-black text-[#1a2b4c]">Order Receipt</h2>
                                <p class="text-sm text-gray-500 mt-0.5">Ref #{{ String(selectedOrder.id).padStart(6, '0') }}</p>
                            </div>
                            <span :class="['px-3 py-1 border rounded-full text-xs font-extrabold', getStatusBadgeClass(selectedOrder.status)]">
                                {{ selectedOrder.status }}
                            </span>
                        </div>

                        <div v-if="selectedOrder.status === 'Rejected' && selectedOrder.rejection_reason" class="bg-red-50 border border-red-100 rounded-xl p-4 mb-6 text-sm text-red-800">
                            <div class="font-bold text-xs uppercase text-red-600 mb-1">Rejection Reason</div>
                            "{{ selectedOrder.rejection_reason }}"
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
                                    <div class="text-[11px] text-violet-700 font-medium truncate max-w-[200px]">{{ selectedOrder.po_attachment.split('/').pop() }}</div>
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

                        <ul class="space-y-4 max-h-60 overflow-y-auto pr-2 mb-4">
                            <li v-for="item in selectedOrder.items" :key="item.id" class="flex justify-between text-sm">
                                <div class="flex-1 pr-4">
                                    <div class="font-bold text-gray-800">
                                        <template v-if="item.product">
                                            <Link :href="route('catalog.show', { product: item.product.id, origin: 'dashboard', order_id: selectedOrder.id })" class="hover:text-[#e96a25] hover:underline transition">
                                                {{ item.product.name }}
                                            </Link>
                                        </template>
                                        <template v-else>
                                            Product Deleted
                                        </template>
                                    </div>
                                    <div class="text-xs text-gray-400">{{ item.quantity }} x {{ formatCurrency(item.price) }}</div>
                                </div>
                                <div class="font-bold text-[#1a2b4c]">{{ formatCurrency(item.price * item.quantity) }}</div>
                            </li>
                        </ul>

                        <div class="mt-6 border-t border-gray-100 pt-4">
                            <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-2">
                                <span class="text-[#1a2b4c] font-black text-lg">Total</span>
                                <span class="text-2xl font-black text-[#e96a25]">{{ formatCurrency(selectedOrder.total) }}</span>
                            </div>
                        </div>
                        <!-- Approval Trail Logs Section -->
                        <div v-if="$page.props.auth.roles.some(r => ['admin', 'supplier_admin', 'supplier_processor', 'supplier_approver'].includes(r)) && (selectedOrder.approval_logs && selectedOrder.approval_logs.length > 0)" class="mt-6 border-t border-gray-100 pt-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Approval Trail Log</h4>
                            <div class="space-y-3 overflow-y-auto max-h-40 pr-1">
                                <div v-for="log in selectedOrder.approval_logs" :key="log.id" class="flex gap-3 text-sm">
                                    <div class="flex-shrink-0 w-7 h-7 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold uppercase text-[10px] border border-indigo-100">
                                        {{ log.user?.name?.charAt(0) }}
                                    </div>
                                    <div class="flex-1 bg-gray-50 rounded-xl p-3 border border-gray-100">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="font-bold text-gray-900 text-xs">{{ log.user?.name }}</span>
                                            <span class="text-[10px] text-gray-400">{{ new Date(log.created_at).toLocaleDateString() }}</span>
                                        </div>
                                        <div class="inline-block text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded border"
                                            :class="log.action.toLowerCase() === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : (log.action.toLowerCase() === 'rejected' ? 'bg-red-50 text-red-700 border-red-100' : 'bg-blue-50 text-blue-700 border-blue-100')">
                                            {{ log.action }}
                                        </div>
                                        <p v-if="log.note" class="text-xs text-gray-600 italic mt-1.5 bg-white/50 p-1.5 rounded">"{{ log.note }}"</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- File Uploader for PO Creation Attachment -->
                        <div v-if="selectedOrder.status === 'Quotation'" class="mt-6 pt-4 border-t border-gray-100">
                            <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                Upload Signed Purchase Order (PO)
                                <span class="text-red-500 font-black text-xs">*</span>
                            </label>
                            
                            <div 
                                class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:border-violet-400 transition duration-200 group relative"
                                :class="statusForm.po_attachment ? 'bg-violet-50/40 border-violet-300' : 'border-gray-200 bg-gray-50/50 hover:bg-gray-50'"
                            >
                                <input 
                                    type="file" 
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" 
                                    accept=".pdf, image/jpeg, image/png, image/jpg"
                                    @change="handleFileChange"
                                />
                                
                                <div v-if="!selectedFileName" class="flex flex-col items-center py-2">
                                    <svg class="w-8 h-8 text-gray-400 mb-2 group-hover:scale-110 transition duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p class="text-xs font-bold text-gray-600">Click to upload file or drag & drop</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Supported formats: PDF, PNG, JPG (Max 5MB)</p>
                                </div>
                                
                                <div v-else class="flex items-center justify-between bg-white p-2.5 rounded-lg border border-violet-100 shadow-sm">
                                    <div class="flex items-center gap-3 min-w-0 text-left">
                                        <div class="w-8 h-8 rounded bg-violet-100 flex items-center justify-center text-violet-600 font-black text-xs flex-shrink-0">
                                            {{ selectedFileName.split('.').pop().toUpperCase() }}
                                        </div>
                                        <div class="truncate pr-2">
                                            <div class="text-xs font-bold text-gray-800 truncate">{{ selectedFileName }}</div>
                                            <div class="text-[9px] text-emerald-600 font-semibold flex items-center gap-1 mt-0.5">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                                File ready
                                            </div>
                                        </div>
                                    </div>
                                    <button 
                                        type="button" 
                                        @click.stop.prevent="() => { selectedFileName = ''; statusForm.po_attachment = null; }"
                                        class="p-1.5 hover:bg-red-50 text-gray-400 hover:text-red-500 rounded-full transition duration-150 flex-shrink-0 z-10"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Error messaging -->
                            <p v-if="fileInputError" class="text-xs text-red-500 font-bold mt-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                {{ fileInputError }}
                            </p>
                            
                            <p v-if="statusForm.errors.po_attachment" class="text-xs text-red-500 font-bold mt-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                {{ statusForm.errors.po_attachment }}
                            </p>
                        </div>

                        <div class="mt-8 space-y-3">
                            <!-- Buyer Action 1: Accept Quotation & Transform into PO -->
                            <button v-if="selectedOrder.status === 'Quotation'" 
                                @click="updateOrderStatus('PO')"
                                :disabled="isUpdating"
                                class="w-full py-3 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold transition duration-200 shadow-md shadow-violet-200/50 flex items-center justify-center space-x-2 disabled:opacity-50">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>{{ isUpdating ? 'Processing...' : 'Approve Quotation (Generate PO)' }}</span>
                            </button>

                            <!-- Buyer Action 2: Finalize Receipt on Shipped inventory -->
                            <button v-if="selectedOrder.status === 'Shipped'" 
                                @click="updateOrderStatus('Completed')"
                                :disabled="isUpdating"
                                class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition duration-200 shadow-md shadow-emerald-200/50 flex items-center justify-center space-x-2 disabled:opacity-50">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span>{{ isUpdating ? 'Processing...' : 'Confirm Goods Received' }}</span>
                            </button>

                            <button @click="closeModal" class="w-full py-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold transition duration-200 text-sm">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Order Status Change Modal -->
        <ConfirmationModal
            :show="showStatusConfirmModal"
            title="Update Order Status"
            :message="`Are you sure you want to mark this order as ${pendingStatus}? This action will impact the current procurement stage.`"
            type="primary"
            confirmLabel="Update Status"
            @close="showStatusConfirmModal = false; pendingStatus = ''"
            @confirm="executeStatusUpdate"
        />
    </AuthenticatedLayout>
</template>
