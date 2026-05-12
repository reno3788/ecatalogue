<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

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

const cartTotal = computed(() => {
    if (!props.cart.items) return 0;
    return props.cart.items.reduce((total, item) => {
        return total + (item.price * item.quantity);
    }, 0);
});

const checkoutForm = useForm({});

const checkout = () => {
    checkoutForm.post(route('checkout.process'), {
        onSuccess: (page) => {
            // Check if it's a punchout return
            if (page.props.flash && page.props.flash.type === 'punchout_return') {
                // In a real scenario, you'd form-post this payload to the return_url
                console.log('PunchOut Return Payload:', page.props.flash.payload);
                alert('PunchOut Session complete. Returning to procurement system...');
                window.location.href = page.props.flash.return_url;
            } else if (page.props.flash && page.props.flash.type === 'direct_order') {
                alert('RFQ generated successfully!');
            }
        }
    });
};

const removeItem = (itemId) => {
    router.delete(route('cart.remove', itemId));
};
</script>

<template>
    <Head title="Shopping Cart" />

    <StoreLayout>
        <div class="w-full">
            <h2 class="font-bold text-2xl mb-6 text-gray-900">Shopping Cart</h2>
            
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm">
                <div v-if="cart.items && cart.items.length > 0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 mb-8">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="item in cart.items" :key="item.id" class="hover:bg-gray-50 transition-colors">
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
                                    <td class="px-4 py-4 text-right text-sm text-gray-700">
                                        {{ formatCurrency(item.price) }}
                                    </td>
                                    <td class="px-4 py-4 text-center text-sm text-gray-900 font-bold">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm font-bold text-gray-900">
                                        {{ formatCurrency(item.price * item.quantity) }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <button @click="removeItem(item.id)" class="text-sm font-medium text-red-500 hover:text-red-700">
                                            Remove
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
                                @click="checkout"
                                :disabled="checkoutForm.processing"
                                class="bg-[#e96a25] hover:bg-[#d55e1d] text-white font-bold py-3 px-8 rounded transition-colors text-lg"
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
    </StoreLayout>
</template>
