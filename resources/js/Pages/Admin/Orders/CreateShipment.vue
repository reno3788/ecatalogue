<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const page = usePage();

const props = defineProps({
    order: {
        type: Object,
        required: true
    },
    carriers: {
        type: Array,
        required: true
    }
});

// Calculate remaining quantities
const getRemainingQty = (item) => {
    const orderQty = Number(item.quantity) || 0;
    const shippedQty = props.order.shipments?.reduce((sum, shipment) => {
        const shItems = shipment.items || [];
        const shItem = shItems.find(si => si.order_item_id === item.id);
        return sum + (shItem ? Number(shItem.quantity) : 0);
    }, 0) || 0;
    return Math.max(0, orderQty - shippedQty);
};

// Initialize form
const form = useForm({
    carrier_id: '',
    tracking_number: '',
    notes: '',
    items: props.order.items.map(item => {
        const rem = getRemainingQty(item);
        return {
            order_item_id: item.id,
            product_name: item.product?.name || 'Deleted Product',
            sku: item.product?.sku || '',
            ordered_qty: item.quantity,
            remaining_qty: rem,
            quantity: rem, // Default to remaining
        };
    })
});

// Calculations & Validations
const totalRemainingToShip = computed(() => {
    return form.items.reduce((sum, item) => sum + item.remaining_qty, 0);
});

const totalCurrentlyShipping = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
});

const isQuantityInvalid = computed(() => {
    return form.items.some(item => {
        const qty = parseInt(item.quantity) || 0;
        return qty > item.remaining_qty || qty < 0;
    });
});

const isFormValid = computed(() => {
    return form.carrier_id && 
           form.tracking_number.trim() !== '' && 
           totalCurrentlyShipping.value > 0 && 
           !isQuantityInvalid.value;
});

// Adjust quantity helpers
const setQuantity = (item, val) => {
    const newQty = Math.min(item.remaining_qty, Math.max(0, val));
    item.quantity = newQty;
};

const submitForm = () => {
    if (!isFormValid.value) return;

    // Filter down to non-zero items for posting
    const cleanedItems = form.items.map(i => ({
        order_item_id: i.order_item_id,
        quantity: parseInt(i.quantity) || 0
    }));

    form.transform((data) => ({
        ...data,
        items: cleanedItems
    })).post(route('admin.orders.create-shipment', props.order.id));
};

const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val);
    } catch (e) {
        return `${currency} ${Number(val).toFixed(2)}`;
    }
};
</script>

<template>
    <Head :title="`Dispatch Shipment - Order #${String(order.id).padStart(6, '0')}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2 text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">
                        <Link :href="route('admin.orders.index')" class="hover:text-[#e96a25] transition-colors">Orders</Link>
                        <span>/</span>
                        <Link :href="route('admin.orders.index', { open_order: order.id })" class="hover:text-[#e96a25] transition-colors">Order #{{ String(order.id).padStart(6, '0') }}</Link>
                        <span>/</span>
                        <span class="text-gray-900">Prepare Shipment</span>
                    </div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Fulfillment & Shipment Dispatch</h2>
                    <p class="text-sm text-gray-500 mt-1">Select items, adjust quantities, select a logistics provider, and generate your Delivery Note packing slip.</p>
                </div>
                
                <Link 
                    :href="route('admin.orders.index', { open_order: order.id })" 
                    class="inline-flex items-center px-4 py-2 border border-gray-200 rounded-xl text-xs font-black uppercase tracking-wider text-gray-500 hover:bg-gray-50 transition"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Order Details
                </Link>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Side: Items selection & details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Overview Card -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block">Buyer Company</span>
                        <h4 class="text-md font-black text-[#1a2b4c]">{{ order.company?.name || 'Loading organization...' }}</h4>
                        <p class="text-xs text-gray-500 font-medium flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {{ order.user?.name || 'Representative' }} ({{ order.user?.email || 'N/A' }})
                        </p>
                    </div>
                    
                    <div class="h-px md:h-12 w-full md:w-px bg-gray-100"></div>

                    <div class="space-y-1 text-left md:text-right">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block">Purchase Order</span>
                        <div class="flex items-center md:justify-end space-x-2">
                            <span class="text-sm font-black text-[#1a2b4c] uppercase tracking-wider">#{{ String(order.id).padStart(6, '0') }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border border-[#e96a25]/20 bg-[#e96a25]/5 text-[#e96a25]">
                                {{ order.status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 font-medium">Total Value: {{ formatCurrency(order.total) }}</p>
                    </div>
                </div>

                <!-- Packing Slip Table -->
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-xs font-black uppercase tracking-wider text-[#1a2b4c]">1. Select Quantities to Dispatch</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Determine the items currently ready for transport. Zero quantities are safely excluded.</p>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div 
                            v-for="item in form.items" 
                            :key="item.order_item_id"
                            class="p-6 transition hover:bg-gray-50/30 flex flex-col md:flex-row md:items-center justify-between gap-6"
                        >
                            <div class="flex-grow space-y-2 max-w-lg">
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-sm text-[#1a2b4c]">{{ item.product_name }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-[10px] font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md uppercase tracking-wider border border-gray-200/50">SKU: {{ item.sku || 'N/A' }}</span>
                                    <span class="text-[10px] font-semibold text-gray-500">Ordered: {{ item.ordered_qty }} units</span>
                                    <span class="text-[10px] font-semibold text-gray-500">Remaining Unshipped: <strong class="text-gray-700 font-extrabold">{{ item.remaining_qty }}</strong></span>
                                </div>

                                <!-- Progress Visual Bar -->
                                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden flex mt-2">
                                    <div 
                                        class="bg-[#e96a25]/30 h-full border-r border-white"
                                        :style="`width: ${((item.ordered_qty - item.remaining_qty) / item.ordered_qty) * 100}%`"
                                        title="Already Shipped"
                                    ></div>
                                    <div 
                                        class="bg-[#e96a25] h-full"
                                        :style="`width: ${(parseInt(item.quantity || 0) / item.ordered_qty) * 100}%`"
                                        title="Current Batch Shipment"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[9px] text-gray-400 font-bold uppercase mt-1">
                                    <span>Shipped: {{ item.ordered_qty - item.remaining_qty }}</span>
                                    <span class="text-[#e96a25]">Active Batch: {{ parseInt(item.quantity) || 0 }}</span>
                                    <span>Remaining Unshipped: {{ Math.max(0, item.remaining_qty - (parseInt(item.quantity) || 0)) }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col items-end shrink-0 gap-2">
                                <div class="flex items-center space-x-1.5">
                                    <!-- Decrement -->
                                    <button 
                                        type="button"
                                        @click="setQuantity(item, parseInt(item.quantity) - 1)"
                                        :disabled="parseInt(item.quantity) <= 0"
                                        class="w-9 h-9 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition active:scale-95 disabled:opacity-40 disabled:pointer-events-none"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                    </button>

                                    <!-- Numeric quantity input -->
                                    <input 
                                        type="number"
                                        v-model.number="item.quantity"
                                        @input="setQuantity(item, item.quantity)"
                                        class="w-16 h-9 text-center text-sm font-bold rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        :class="{'border-red-500 ring-red-100 bg-red-50/20 text-red-700': item.quantity > item.remaining_qty || item.quantity < 0}"
                                    />

                                    <!-- Increment -->
                                    <button 
                                        type="button"
                                        @click="setQuantity(item, parseInt(item.quantity) + 1)"
                                        :disabled="parseInt(item.quantity) >= item.remaining_qty"
                                        class="w-9 h-9 rounded-xl border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition active:scale-95 disabled:opacity-40 disabled:pointer-events-none"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    </button>
                                </div>
                                
                                <span v-if="item.quantity > item.remaining_qty" class="text-[10px] text-red-500 font-bold uppercase tracking-wider animate-pulse">
                                    Exceeds Remaining (Max {{ item.remaining_qty }})
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Carrier, Tracking, Notes, Submit Actions -->
            <div class="space-y-6">
                <!-- Logistics Form -->
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden p-6 space-y-5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-[#1a2b4c] border-b border-gray-100 pb-3">2. Shipment Logistics</h3>

                    <!-- Carrier Dropdown -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Logistics Carrier Provider</label>
                        <select 
                            v-model="form.carrier_id" 
                            required
                            class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-3"
                        >
                            <option value="" disabled>Select active carrier...</option>
                            <option v-for="c in carriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <span v-if="form.errors.carrier_id" class="text-xs text-red-500 mt-1 block">{{ form.errors.carrier_id }}</span>
                    </div>

                    <!-- Tracking/Waybill Code -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tracking Waybill ID</label>
                        <input 
                            type="text" 
                            v-model="form.tracking_number" 
                            required
                            placeholder="e.g. WH-8273612-US"
                            class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-3"
                        />
                        <span v-if="form.errors.tracking_number" class="text-xs text-red-500 mt-1 block">{{ form.errors.tracking_number }}</span>
                    </div>

                    <!-- Annotation Notes -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Shipping Memo & Notes</label>
                        <textarea 
                            v-model="form.notes"
                            rows="4"
                            placeholder="Optional shipping notes, handling guidelines, custom annotations, packaging remarks..."
                            class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                        ></textarea>
                        <span v-if="form.errors.notes" class="text-xs text-red-500 mt-1 block">{{ form.errors.notes }}</span>
                    </div>
                </div>

                <!-- Submit Action Panel -->
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-[#1a2b4c] border-b border-gray-100 pb-3">3. Dispatch Summary</h3>
                    
                    <div class="space-y-2 text-xs font-medium text-gray-600">
                        <div class="flex justify-between">
                            <span>Remaining Unshipped items:</span>
                            <span class="font-extrabold text-gray-900">{{ totalRemainingToShip }} units</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Dispatching in this batch:</span>
                            <span class="font-extrabold text-[#e96a25]">{{ totalCurrentlyShipping }} units</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Remaining after dispatch:</span>
                            <span class="font-extrabold text-gray-900">{{ Math.max(0, totalRemainingToShip - totalCurrentlyShipping) }} units</span>
                        </div>
                    </div>

                    <div class="h-px bg-gray-100"></div>

                    <!-- Error warnings -->
                    <div v-if="totalCurrentlyShipping === 0" class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-xl p-3.5 font-semibold leading-relaxed flex items-start space-x-2">
                        <svg class="w-4 h-4 mt-0.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Please select at least 1 item to ship in this batch shipment.</span>
                    </div>

                    <div v-else-if="isQuantityInvalid" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-xl p-3.5 font-semibold leading-relaxed flex items-start space-x-2">
                        <svg class="w-4 h-4 mt-0.5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Quantities entered exceed the maximum remaining unshipped quantities. Please adjust.</span>
                    </div>

                    <button 
                        type="button" 
                        @click="submitForm"
                        :disabled="!isFormValid || form.processing"
                        class="w-full py-3.5 px-4 rounded-xl text-xs font-black uppercase tracking-wider text-white bg-gradient-to-r from-[#e96a25] to-orange-500 shadow-md hover:from-orange-500 hover:to-orange-600 active:scale-[0.98] transition flex items-center justify-center space-x-2 disabled:opacity-40 disabled:scale-100 disabled:pointer-events-none"
                    >
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span v-if="form.processing">Processing Shipment...</span>
                        <span v-else>Dispatched & Generate PDF</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
