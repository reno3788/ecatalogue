<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { ref, computed } from 'vue';

const page = usePage();

const canAddToCart = computed(() => {
    const restrictedRoles = ['supplier_approver', 'supplier_processor', 'supplier_admin'];
    return !page.props.auth?.roles?.some(r => restrictedRoles.includes(r));
});
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
    product: {
        type: Object,
        required: true
    }
});

const form = useForm({
    product_id: props.product.id,
    quantity: Math.max(1, props.product.minimum_order || 1)
});

const addedToCart = ref(false);

const addToCart = () => {
    form.post(route('cart.add'), {
        preserveScroll: true,
        onSuccess: () => {
            addedToCart.value = true;
            setTimeout(() => {
                addedToCart.value = false;
            }, 2000);
        }
    });
};

const updateQuantity = (change) => {
    const minLimit = Math.max(1, props.product.minimum_order || 1);
    const newVal = form.quantity + change;
    if (newVal >= minLimit) {
        form.quantity = newVal;
    }
};

const validateQuantity = () => {
    const minLimit = Math.max(1, props.product.minimum_order || 1);
    if (!form.quantity || form.quantity < minLimit) {
        form.quantity = minLimit;
    }
};

const generatePlaceholder = (id) => {
    return `https://picsum.photos/seed/${id}/600/600`;
};

// Lightbox functionality
const isLightboxOpen = ref(false);
const activeImage = ref('');

const openLightbox = (imgUrl) => {
    activeImage.value = imgUrl;
    isLightboxOpen.value = true;
};

const closeLightbox = () => {
    isLightboxOpen.value = false;
};

// Gather all images (main + gallery)
const allImages = [
    props.product.image || generatePlaceholder(props.product.id),
    ...(props.product.images?.map(img => img.image_path) || [])
];

const breadcrumbItems = computed(() => {
    // Fallback for server side render
    if (typeof window === 'undefined') {
        return [{ label: 'Catalog', href: route('catalog.index'), icon: 'catalog' }];
    }
    
    const params = new URLSearchParams(window.location.search);
    const origin = params.get('origin');
    const orderId = params.get('order_id');

    if (origin === 'orders') {
        return [
            { label: 'Dashboard', href: route('dashboard'), icon: 'dashboard' },
            { label: 'My Orders', href: route('orders.index') },
            { label: orderId ? `Order #${orderId}` : 'Order Detail', href: route('orders.index', orderId ? { open_order: orderId } : {}) }
        ];
    }
    if (origin === 'dashboard') {
        return [
            { label: 'Dashboard', href: route('dashboard'), icon: 'dashboard' },
            { label: orderId ? `Order #${orderId}` : 'Order Detail', href: route('dashboard', orderId ? { open_order: orderId } : {}) }
        ];
    }
    if (origin === 'admin-orders') {
        return [
            { label: 'Dashboard', href: route('admin.dashboard'), icon: 'dashboard' },
            { label: 'Orders', href: route('admin.orders.index') },
            { label: orderId ? `Order #${orderId}` : 'Order Detail', href: route('admin.orders.index', orderId ? { open_order: orderId } : {}) }
        ];
    }
    return [
        { label: 'Catalog', href: route('catalog.index'), icon: 'catalog' }
    ];
});

</script>

<template>
    <Head :title="product.name" />

    <StoreLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <!-- Breadcrumbs -->
            <Breadcrumbs :items="breadcrumbItems" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <!-- Product Images -->
                <div>
                    <!-- Main Image -->
                    <div 
                        @click="openLightbox(allImages[0])"
                        class="aspect-w-1 aspect-h-1 bg-white border border-gray-200 rounded-2xl flex items-center justify-center p-8 mb-4 cursor-zoom-in hover:shadow-lg transition-shadow"
                    >
                        <img :src="allImages[0]" :alt="product.name" class="object-contain max-h-96">
                    </div>
                    
                    <!-- Thumbnails -->
                    <div class="grid grid-cols-4 gap-4" v-if="allImages.length > 1">
                        <div v-for="(img, index) in allImages" :key="index"
                             @click="openLightbox(img)"
                             class="aspect-w-1 aspect-h-1 bg-white border border-gray-200 rounded-lg flex items-center justify-center p-2 cursor-pointer hover:border-[#e96a25] transition-colors">
                            <img :src="img" :alt="`${product.name} thumbnail ${index + 1}`" class="object-contain max-h-20">
                        </div>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="flex flex-col">
                    <div class="mb-6">
                        <div class="text-sm text-gray-500 mb-2 font-medium tracking-wide uppercase">
                            {{ product.brand || 'No Brand' }}
                        </div>
                        <h1 class="text-3xl font-extrabold text-[#1a2b4c] leading-tight mb-2">{{ product.name }}</h1>
                        <div class="text-sm text-gray-500">
                            SKU: {{ product.sku }}
                        </div>
                    </div>

                    <div class="mb-8">
                        <span class="text-4xl font-extrabold text-gray-900">{{ formatCurrency(product.base_price) }}</span>
                    </div>

                    <div class="prose prose-sm text-gray-600 mb-8 border-t border-b border-gray-100 py-6">
                        <p>{{ product.description || 'No detailed description available for this product.' }}</p>
                        
                        <div class="mt-6 grid grid-cols-2 gap-4 text-sm" v-if="product.weight || product.color || product.size || product.uom">
                            <div v-if="product.weight">
                                <span class="font-bold text-gray-900 block">Weight</span>
                                {{ product.weight }}
                            </div>
                            <div v-if="product.color">
                                <span class="font-bold text-gray-900 block">Color</span>
                                {{ product.color }}
                            </div>
                            <div v-if="product.size">
                                <span class="font-bold text-gray-900 block">Size</span>
                                {{ product.size }}
                            </div>
                            <div v-if="product.uom">
                                <span class="font-bold text-gray-900 block">UOM</span>
                                {{ product.uom }}
                            </div>
                            <div v-if="product.minimum_order > 1">
                                <span class="font-bold text-red-600 block">Min. Order</span>
                                {{ product.minimum_order }}
                            </div>
                        </div>
                    </div>

                    <!-- Add to Cart Form -->
                    <div v-if="canAddToCart" class="mt-auto bg-gray-50 p-6 rounded-xl border border-gray-100">
                        <div class="flex items-center space-x-4 mb-4">
                            <span class="font-bold text-gray-900">Quantity</span>
                            <div class="flex items-center border border-gray-300 rounded-lg h-12 w-32 bg-white">
                                <button @click="updateQuantity(-1)" class="px-4 text-gray-500 hover:text-gray-900 transition-colors">-</button>
                                <input type="number" v-model.number="form.quantity" :min="Math.max(1, product.minimum_order || 1)" @change="validateQuantity" class="w-full text-center border-none focus:ring-0 p-0 font-medium" />
                                <button @click="updateQuantity(1)" class="px-4 text-gray-500 hover:text-gray-900 transition-colors">+</button>
                            </div>
                        </div>
                        
                        <button 
                            @click="addToCart"
                            :disabled="form.processing"
                            class="w-full h-14 flex items-center justify-center rounded-lg text-white text-lg font-bold transition-all duration-300"
                            :class="addedToCart ? 'bg-green-600 hover:bg-green-700 shadow-lg shadow-green-600/30' : 'bg-[#e96a25] hover:bg-[#d85a15] hover:shadow-xl hover:shadow-[#e96a25]/30'"
                        >
                            <svg v-if="addedToCart" class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <svg v-else class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            {{ addedToCart ? 'Added to Cart' : 'Add to Cart' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="isLightboxOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click="closeLightbox">
                    <button @click="closeLightbox" class="absolute top-6 right-6 text-white hover:text-gray-300 p-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    
                    <img :src="activeImage" class="max-w-full max-h-[90vh] object-contain rounded" @click.stop>
                </div>
            </Transition>
        </Teleport>
    </StoreLayout>
</template>
