<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const page = usePage();
const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(val);
    } catch (e) {
        return `${currency} ${Number(val).toFixed(2)}`;
    }
};

const props = defineProps({
    cart: {
        type: Object,
        required: true,
    },
});

// 🚀 Reactive Local State for 100% instant zero-latency SPA updates!
const items = ref([]);

// Synchronise internal registry with updated Inertia props automatically
watch(() => props.cart.items, (newVal) => {
    items.value = JSON.parse(JSON.stringify(newVal || []));
}, { immediate: true, deep: true });

// Calculate dynamically using local reactive state
const cartTotal = computed(() => {
    return items.value.reduce((total, item) => {
        return total + (item.price * item.quantity);
    }, 0);
});

const checkoutForm = useForm({});

const punchoutModal = ref({
    show: false,
    returnUrl: ''
});

const executePunchoutRedirect = () => {
    window.location.href = punchoutModal.value.returnUrl;
};

const checkout = () => {
    checkoutForm.post(route('checkout.process'), {
        onSuccess: (page) => {
            if (page.props.flash && page.props.flash.type === 'punchout_return') {
                console.log('PunchOut Return Payload:', page.props.flash.payload);
                punchoutModal.value = {
                    show: true,
                    returnUrl: page.props.flash.return_url
                };
            }
        }
    });
};

const showCheckoutConfirm = ref(false);

const openCheckoutConfirm = () => {
    showCheckoutConfirm.value = true;
};

const executeCheckout = () => {
    showCheckoutConfirm.value = false;
    checkout();
};

// --- Responsive UI Mechanics via Pure JS Reactivity ---

const updateQty = (item, newQty) => {
    const min = Math.max(1, item.product.minimum_order || 1);
    if (newQty < min) return;
    
    // ⚡ Optimistic Upgrade: Update client side state instantly!
    const target = items.value.find(i => i.id === item.id);
    if (target) {
        target.quantity = newQty;
    }
    
    // Sync in background without blocker
    router.patch(route('cart.update', item.id), {
        quantity: newQty
    }, {
        preserveScroll: true,
        preserveState: true, // Prevent state resetting mid-flight
        onError: () => {
            // Rollback on network failure
            items.value = JSON.parse(JSON.stringify(props.cart.items));
        }
    });
};

// --- Sleek Confirmation Modal Controllers ---
const deleteModal = ref({
    isOpen: false,
    itemId: null,
    itemName: ''
});

const removeItem = (itemId) => {
    const item = items.value.find(i => i.id === itemId);
    if (item) {
        deleteModal.value = {
            isOpen: true,
            itemId: itemId,
            itemName: item.product.name
        };
    }
};

const closeDeleteModal = () => {
    deleteModal.value.isOpen = false;
};

const confirmDelete = () => {
    const itemId = deleteModal.value.itemId;
    closeDeleteModal();
    
    // ⚡ Optimistic Deletion: Clear row immediately for instant feedback!
    items.value = items.value.filter(i => i.id !== itemId);
    
    router.delete(route('cart.remove', itemId), {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            // Rollback on network failure
            items.value = JSON.parse(JSON.stringify(props.cart.items));
        }
    });
};
</script>

<template>
    <Head title="Shopping Cart" />

    <StoreLayout>
        <div class="w-full">
            <h2 class="font-bold text-2xl mb-6 text-gray-900">Shopping Cart</h2>
            
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm relative overflow-hidden">
                <!-- Loading Overlay for Checkout Process -->
                <Transition name="fade">
                    <div v-if="checkoutForm.processing" class="absolute inset-0 z-30 bg-white/60 backdrop-blur-[2px] flex flex-col items-center justify-center space-y-4 transition-all duration-300">
                        <div class="flex flex-col items-center space-y-3 bg-white shadow-2xl rounded-2xl px-10 py-8 border border-gray-100 mx-4">
                            <div class="relative flex items-center justify-center">
                                <div class="w-12 h-12 border-4 border-amber-100 border-t-[#e96a25] rounded-full animate-spin"></div>
                                <svg class="w-6 h-6 text-[#e96a25] absolute" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-black text-[#1a2b4c] tracking-wide uppercase">Processing Checkout...</p>
                                <p class="text-[10px] text-gray-400 font-bold tracking-widest animate-pulse mt-1">Submitting Request for Quotation</p>
                            </div>
                        </div>
                    </div>
                </Transition>

                <div v-if="items.length > 0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 mb-8">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">UoM</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-16 w-16 border border-gray-200 bg-white p-1 rounded">
                                                <img v-if="item.product.image" class="h-full w-full object-contain" :src="item.product.image" alt="">
                                                <div v-else class="h-full w-full bg-gray-50 flex items-center justify-center text-[10px] text-gray-400 text-center">No Img</div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900">{{ item.product.name }}</div>
                                                <div class="text-xs text-gray-500 mt-1">{{ item.product.sku }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div class="inline-flex items-center bg-gray-50 border border-gray-200 rounded shadow-sm overflow-hidden">
                                            <button 
                                                @click="updateQty(item, item.quantity - 1)" 
                                                :disabled="item.quantity <= Math.max(1, item.product.minimum_order || 1)" 
                                                class="px-2.5 py-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors font-bold border-r border-gray-200 text-sm"
                                                type="button"
                                            >
                                                -
                                            </button>
                                            <span class="w-10 text-center text-sm font-bold text-gray-900 select-none">
                                                {{ item.quantity }}
                                            </span>
                                            <button 
                                                @click="updateQty(item, item.quantity + 1)" 
                                                class="px-2.5 py-1.5 text-gray-500 hover:bg-gray-100 transition-colors font-bold border-l border-gray-200 text-sm"
                                                type="button"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center text-sm text-gray-600 font-semibold">
                                        {{ item.product.uom || '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-700">
                                        {{ formatCurrency(item.price) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm font-bold text-gray-900">
                                        {{ formatCurrency(item.price * item.quantity) }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <button @click="removeItem(item.id)" class="p-2 inline-flex items-center justify-center bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-600 rounded-full transition-colors duration-200" title="Remove Item">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col items-end border-t border-gray-200 pt-6 mt-2">
                        <div class="flex items-center justify-between w-full md:w-1/3 mb-6 bg-gray-50 p-4 rounded border border-gray-200">
                            <span class="text-lg font-bold text-gray-700">Total:</span>
                            <span class="text-2xl font-bold text-[#e96a25]">{{ formatCurrency(cartTotal) }}</span>
                        </div>

                        <div class="flex items-center gap-6">
                            <Link :href="route('catalog.index')" class="text-sm font-bold text-gray-500 hover:text-[#e96a25] transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Continue Shopping
                            </Link>
                            <button 
                                @click="openCheckoutConfirm"
                                :disabled="checkoutForm.processing"
                                class="bg-[#e96a25] hover:bg-[#d55e1d] text-white font-bold py-3 px-8 rounded transition-colors text-lg shadow-md shadow-amber-50 hover:shadow-lg duration-200 active:scale-[0.98]"
                            >
                                Create RFQ
                            </button>
                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-16">
                    <div class="text-gray-500 mb-6">
                        Your cart is empty.
                    </div>
                    <Link :href="route('catalog.index')" class="inline-block bg-[#1a2b4c] hover:bg-[#121f38] text-white font-bold py-2.5 px-6 rounded transition-colors shadow-sm">
                        Continue Shopping
                    </Link>
                </div>
            </div>
        </div>

        <!-- Modern Confirmation Modal for Item Deletion -->
        <Transition name="fade">
            <div v-if="deleteModal.isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-x-hidden overflow-y-auto outline-none focus:outline-none">
                <!-- Semi-Transparent Glass Backdrop -->
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeDeleteModal"></div>
                
                <!-- Premium Pop Card -->
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 z-50 border border-slate-100 flex flex-col gap-4 scale-100 duration-200 transition-all animate-[slide-up_0.3s_cubic-bezier(0.34,1.56,0.64,1)]">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-rose-50 rounded-full flex-shrink-0 text-rose-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 leading-tight">Remove Item?</h3>
                            <p class="text-sm text-slate-500 leading-relaxed mt-1.5">
                                Are you sure you want to remove <span class="font-bold text-slate-800">"{{ deleteModal.itemName }}"</span> from your shopping cart?
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-3 border-t border-slate-100 pt-4">
                        <button @click="closeDeleteModal" type="button" class="px-4 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-lg transition-colors text-sm">
                            Keep It
                        </button>
                        <button @click="confirmDelete" type="button" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg shadow-sm transition-colors text-sm flex items-center gap-2">
                            Remove Now
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Premium Punchout Redirection Modal -->
        <ConfirmationModal
            :show="punchoutModal.show"
            title="PunchOut Session Ready"
            message="Your procurement cart transfer is complete. Click below to transmit information and return securely to your internal ERP ecosystem."
            type="success"
            confirmLabel="Return to System"
            :hide-cancel="true"
            @confirm="executePunchoutRedirect"
            @close="executePunchoutRedirect"
        />

        <!-- Premium Direct Order (RFQ) Confirmation Modal -->
        <ConfirmationModal
            :show="showCheckoutConfirm"
            title="Submit Request for Quotation?"
            message="Are you ready to submit these items to our procurement team? We will process your request immediately and notify you with an official quotation."
            type="info"
            confirmLabel="Submit RFQ"
            cancelLabel="Keep Editing"
            @confirm="executeCheckout"
            @close="showCheckoutConfirm = false"
        />
    </StoreLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
@keyframes slide-up {
  from {
    transform: translateY(12px) scale(0.97);
    opacity: 0;
  }
  to {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}
</style>
