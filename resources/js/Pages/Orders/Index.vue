<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    orders: Object,
    filters: Object,
    statuses: Array,
});

const page = usePage();
const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val); }
    catch (e) { return `${currency} ${Number(val).toFixed(2)}`; }
};

// Filter states
const filterMonth = ref(props.filters.month || '');
const filterStatus = ref(props.filters.status || '');

// Inertia Watch reload
watch([filterMonth, filterStatus], () => {
    router.get(route('orders.index'), {
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

// Modal logic
const showModal = ref(false);
const selectedOrder = ref(null);
const loadingDetails = ref(false);

const openDetails = async (orderId) => {
    showModal.value = true;
    loadingDetails.value = true;
    try {
        // Note: using non-admin named route here
        const res = await axios.get(route('orders.show', orderId));
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

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'Approved': return 'bg-indigo-50 text-indigo-700 border-indigo-100';
        case 'Finished': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'Rejected': return 'bg-red-50 text-red-700 border-red-100';
        case 'pending': return 'bg-yellow-50 text-yellow-700 border-yellow-100';
        case 'Submitted':
        default: return 'bg-blue-50 text-blue-700 border-blue-100';
    }
};
</script>

<template>
    <Head title="Order History" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-extrabold text-2xl text-[#1a2b4c] leading-tight tracking-tight">My Order History</h2>
                    <p class="text-sm text-gray-500 mt-1">View previous orders placed for your company.</p>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-6xl mx-auto space-y-6">
            
            <!-- Basic Info Alert if user exists and belongs to company -->
            <div v-if="$page.props.auth.user?.company_id" class="bg-gradient-to-r from-[#1a2b4c] to-[#274075] p-5 rounded-2xl shadow-sm flex items-center justify-between text-white">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-indigo-200 opacity-80">Signed-in Entity</p>
                    <h3 class="text-lg font-black tracking-tight">Company ID Access Root</h3>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 border border-white/20 text-sm font-bold backdrop-blur-sm">
                        <svg class="w-4 h-4 mr-1.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 4.925-3.075 9.136-7.39 10.782A.996.996 0 0110 18c-.231 0-.459-.04-.675-.117C5.01 16.248 2 12.008 2 7.083c0-.71.06-1.411.166-2.084zM10 5a2 2 0 100 4 2 2 0 000-4z" clip-rule="evenodd" /></svg>
                        Authorized
                    </span>
                </div>
            </div>

            <!-- Small Filter Bar -->
            <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm flex flex-wrap md:flex-nowrap items-end gap-4">
                <div class="w-full md:w-48">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 tracking-wide">Month</label>
                    <input type="month" v-model="filterMonth" class="w-full border-gray-200 rounded-lg text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm" />
                </div>
                <div class="w-full md:w-48">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 tracking-wide">Status</label>
                    <select v-model="filterStatus" class="w-full border-gray-200 rounded-lg text-sm focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 shadow-sm">
                        <option value="">All Statuses</option>
                        <option v-for="st in statuses" :key="st" :value="st">{{ st }}</option>
                    </select>
                </div>
                <div class="flex-grow md:flex-grow-0 md:ml-auto">
                    <button @click="resetFilters" class="text-xs font-bold text-gray-500 hover:text-[#e96a25] px-2 py-2 transition">Clear Filters</button>
                </div>
            </div>

            <!-- List view table -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-50">
                        <thead class="bg-gray-50/50 text-left">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Order Ref</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Date Placed</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400">Ordered By</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-right">Grand Total</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-center">Status</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-400 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 bg-white">
                            <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50/50 transition-colors duration-150">
                                <td class="px-6 py-5">
                                    <span class="font-black text-gray-900 text-sm">#{{ String(order.id).padStart(6, '0') }}</span>
                                </td>
                                <td class="px-6 py-5 text-sm font-medium text-gray-600">
                                    {{ new Date(order.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-bold text-gray-800 text-sm">{{ order.user?.name }}</div>
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
                                        <span>View Receipt</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="orders.data.length === 0">
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center text-gray-400">
                                        <svg class="w-12 h-12 mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                        <p class="font-semibold">No orders found matching current filters.</p>
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

                        <!-- Display rejection reason if present -->
                        <div v-if="selectedOrder.status === 'Rejected' && selectedOrder.rejection_reason" class="bg-red-50 border border-red-100 rounded-xl p-4 mb-6 text-sm text-red-800">
                            <div class="font-bold text-xs uppercase text-red-600 mb-1">Reason for Rejection</div>
                            "{{ selectedOrder.rejection_reason }}"
                        </div>

                        <div class="text-xs font-bold text-gray-400 uppercase mb-4 flex justify-between items-center">
                            <span>Items Breakdown</span>
                            <span class="normal-case font-medium">{{ new Date(selectedOrder.created_at).toLocaleDateString() }}</span>
                        </div>

                        <ul class="space-y-4">
                            <li v-for="item in selectedOrder.items" :key="item.id" class="flex justify-between text-sm">
                                <div class="flex-1 pr-4">
                                    <template v-if="item.product">
                                        <Link :href="route('catalog.show', item.product.id)" class="font-bold text-gray-800 hover:text-[#e96a25] hover:underline transition">{{ item.product.name }}</Link>
                                    </template>
                                    <template v-else>
                                        <div class="font-bold text-gray-400">Deleted product</div>
                                    </template>
                                    <div class="text-xs text-gray-400 mt-0.5">{{ item.quantity }} x {{ formatCurrency(item.price) }}</div>
                                </div>
                                <div class="font-bold text-[#1a2b4c] whitespace-nowrap">
                                    {{ formatCurrency(item.price * item.quantity) }}
                                </div>
                            </li>
                        </ul>

                        <div class="mt-6 border-t border-gray-100 pt-4">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-gray-500 text-sm font-semibold">Subtotal</span>
                                <span class="text-gray-700 font-bold">{{ formatCurrency(selectedOrder.total) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-2">
                                <span class="text-[#1a2b4c] font-black text-lg">Total Payment</span>
                                <span class="text-2xl font-black text-[#e96a25]">{{ formatCurrency(selectedOrder.total) }}</span>
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
