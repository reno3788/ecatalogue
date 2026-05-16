<script setup>
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';

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
const canAddToCart = computed(() => {
    const restrictedRoles = ['supplier_approver', 'supplier_processor', 'supplier_admin'];
    return !page.props.auth?.roles?.some(r => restrictedRoles.includes(r));
});

const currencySymbol = computed(() => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try {
        const formatter = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        return formatter.format(0).replace(/\d/g, '').trim();
    } catch (e) {
        return currency;
    }
});

const rootStoreCategories = computed(() => {
    return props.categories.filter(c => !c.parent_id);
});

const getStoreChildren = (parentId) => {
    return props.categories.filter(c => c.parent_id === parentId);
};

const props = defineProps({
    products: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    brands: {
        type: Array,
        required: true,
    },
    priceRange: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    }
});

const form = useForm({
    product_id: '',
    quantity: 1,
});

const addedProduct = ref(null);
const animatingProduct = ref(null);
const quantities = ref({});

// Initialize quantities to minimum order limit or default 1 for all products
const initQuantities = () => {
    props.products.forEach(p => {
        if (!(p.id in quantities.value)) {
            quantities.value[p.id] = Math.max(1, p.minimum_order || 1);
        }
    });
};
initQuantities();
watch(() => props.products, initQuantities, { immediate: true });

const updateQuantity = (productId, delta) => {
    const prod = props.products.find(p => p.id === productId);
    const minLimit = prod ? Math.max(1, prod.minimum_order || 1) : 1;
    const current = quantities.value[productId] || minLimit;
    if (current + delta >= minLimit) {
        quantities.value[productId] = current + delta;
    }
};

const validateQuantity = (product) => {
    const minLimit = Math.max(1, product.minimum_order || 1);
    let current = quantities.value[product.id];
    if (!current || current < minLimit) {
        quantities.value[product.id] = minLimit;
    }
};

const addToCart = (product) => {
    form.product_id = product.id;
    form.quantity = quantities.value[product.id] || 1;
    
    // Trigger animation
    animatingProduct.value = product.id;
    
    form.post(route('cart.add'), {
        preserveScroll: true,
        onSuccess: () => {
            addedProduct.value = product.id;
            setTimeout(() => {
                if (addedProduct.value === product.id) {
                    addedProduct.value = null;
                }
                if (animatingProduct.value === product.id) {
                    animatingProduct.value = null;
                }
            }, 1000);
        },
        onError: () => {
             animatingProduct.value = null;
        }
    });
};

// Filters logic
const isLoading = ref(false);
let debounceTimeout = null;

const filterState = reactive({
    category: props.filters.category || null,
    q: props.filters.q || '',
    brands: Array.isArray(props.filters.brands) ? [...props.filters.brands] : [],
    min_price: props.filters.min_price || props.priceRange.min,
    max_price: props.filters.max_price || props.priceRange.max,
    sort: props.filters.sort || 'relevance'
});

const updateFilters = (debounce = false) => {
    if (debounceTimeout) clearTimeout(debounceTimeout);
    
    const runRequest = () => {
        isLoading.value = true;
        router.get(route('catalog.index'), filterState, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isLoading.value = false;
            },
            onError: () => {
                isLoading.value = false;
            }
        });
    };
    
    if (debounce) {
        debounceTimeout = setTimeout(runRequest, 400);
    } else {
        runRequest();
    }
};

watch(() => filterState.q, () => updateFilters(true));
watch(() => filterState.min_price, () => updateFilters(true));
watch(() => filterState.max_price, () => updateFilters(true));
watch(() => filterState.category, () => updateFilters(false));
watch(() => filterState.brands, () => updateFilters(false), { deep: true });
watch(() => filterState.sort, () => updateFilters(false));

const setCategory = (slug) => {
    filterState.category = slug;
};

// Drill down / expand categories state
const expandedCategories = ref([]);

const toggleExpand = (catId) => {
    if (expandedCategories.value.includes(catId)) {
        expandedCategories.value = expandedCategories.value.filter(id => id !== catId);
    } else {
        expandedCategories.value.push(catId);
    }
};

const isExpanded = (catId) => {
    return expandedCategories.value.includes(catId);
};

// Auto-expand parents of active child categories recursively
watch(() => filterState.category, (newSlug) => {
    if (newSlug) {
        const activeCat = props.categories.find(c => c.slug === newSlug);
        if (activeCat && activeCat.parent_id) {
            // Expand parent
            if (!expandedCategories.value.includes(activeCat.parent_id)) {
                expandedCategories.value.push(activeCat.parent_id);
            }
            // Check if parent itself has a parent (grandparent) and expand it
            const parentCat = props.categories.find(c => c.id === activeCat.parent_id);
            if (parentCat && parentCat.parent_id) {
                if (!expandedCategories.value.includes(parentCat.parent_id)) {
                    expandedCategories.value.push(parentCat.parent_id);
                }
            }
        }
    }
}, { immediate: true });

// View toggle
const viewMode = ref('grid');

// Mobile Sidebar toggle
const showSidebar = ref(false);

const generatePlaceholder = (id) => `https://picsum.photos/seed/${id}/400/400`;

</script>

<style>
/* Grid/List transition animations */
.list-move, /* apply transition to moving elements */
.list-enter-active,
.list-leave-active {
  transition: all 0.5s ease;
}

.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateY(30px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.list-leave-active {
  position: absolute;
}
</style>

<template>
    <Head title="E-Catalogue" />

    <StoreLayout :search-query="filterState.q" :current-category="filterState.category">
        
        <div class="flex items-center justify-between md:hidden mb-4 bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
            <h2 class="font-bold text-gray-800">Filters</h2>
            <button @click="showSidebar = !showSidebar" class="p-2 bg-gray-100 rounded text-gray-600 hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
        </div>

        <!-- Sidebar Filters -->
        <div :class="{'hidden md:block': !showSidebar, 'block': showSidebar}" class="w-full md:w-64 flex-shrink-0 bg-white border border-gray-100 rounded-lg p-5 shadow-sm h-fit">
            
            <!-- Categories -->
            <h3 class="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider">Categories</h3>
            <ul class="space-y-2 mb-8">
                <li>
                    <button 
                        @click="setCategory(null)"
                        class="w-full flex justify-between items-center text-sm py-1 hover:text-[#e96a25] transition-colors"
                        :class="!filterState.category ? 'font-bold text-gray-900' : 'text-gray-600'"
                    >
                        <span>All Products</span>
                    </button>
                </li>
                <li v-for="cat in rootStoreCategories" :key="cat.id" class="space-y-1.5">
                    <div class="flex items-center justify-between group">
                        <button 
                            @click="setCategory(cat.slug)"
                            class="flex-grow text-left text-sm py-1.5 hover:text-[#e96a25] transition-colors flex items-center gap-2"
                            :class="filterState.category === cat.slug ? 'font-bold text-gray-900' : 'text-gray-600'"
                        >
                            <!-- Elegant category list dot indicator -->
                            <span class="w-2 h-2 rounded-full transition-all duration-200" :class="filterState.category === cat.slug ? 'bg-[#e96a25] scale-110 shadow-sm' : 'bg-gray-300 group-hover:bg-[#e96a25]'"></span>
                            <span>{{ cat.name }}</span>
                            <span class="text-gray-400 text-[10px] font-medium" v-if="cat.products_count > 0">({{ cat.products_count }})</span>
                        </button>
                        
                        <!-- Premium drill down chevron button -->
                        <button 
                            v-if="getStoreChildren(cat.id).length > 0"
                            @click.stop="toggleExpand(cat.id)"
                            class="p-1 hover:bg-gray-100 rounded text-gray-400 hover:text-gray-700 transition-colors"
                        >
                            <svg 
                                class="w-4 h-4 transform transition-transform duration-200" 
                                :class="{ 'rotate-90 text-[#e96a25]': isExpanded(cat.id) }" 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Nested Children with drill-down transition -->
                    <ul v-if="getStoreChildren(cat.id).length > 0 && isExpanded(cat.id)" class="pl-5 border-l border-dashed border-gray-200 space-y-1.5 py-1">
                        <li v-for="child in getStoreChildren(cat.id)" :key="child.id" class="space-y-1">
                            <div class="flex items-center justify-between group/sub">
                                <button 
                                    @click="setCategory(child.slug)"
                                    class="flex-grow text-left text-xs py-1 hover:text-[#e96a25] transition-colors flex items-center gap-2"
                                    :class="filterState.category === child.slug ? 'font-bold text-[#e96a25]' : 'text-gray-500'"
                                >
                                    <!-- Subcategory bullet line -->
                                    <span class="w-1.5 h-1.5 rounded-full transition-all" :class="filterState.category === child.slug ? 'bg-[#e96a25] scale-110' : 'bg-gray-300 group-hover/sub:bg-[#e96a25]'"></span>
                                    <span>{{ child.name }}</span>
                                    <span class="text-gray-400 text-[9px]" v-if="child.products_count > 0">({{ child.products_count }})</span>
                                </button>
                                
                                <!-- Level 2 drill down chevron button if child has grandchildren -->
                                <button 
                                    v-if="getStoreChildren(child.id).length > 0"
                                    @click.stop="toggleExpand(child.id)"
                                    class="p-0.5 hover:bg-gray-100 rounded text-gray-400 hover:text-gray-600 transition-colors"
                                >
                                    <svg 
                                        class="w-3.5 h-3.5 transform transition-transform duration-200" 
                                        :class="{ 'rotate-90 text-[#e96a25]': isExpanded(child.id) }" 
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Level 3 Grandchildren (e.g. Keyboard and Mouse) -->
                            <ul v-if="getStoreChildren(child.id).length > 0 && isExpanded(child.id)" class="pl-4 border-l border-dotted border-gray-200 space-y-1 py-0.5">
                                <li v-for="deepChild in getStoreChildren(child.id)" :key="deepChild.id">
                                    <button 
                                        @click="setCategory(deepChild.slug)"
                                        class="w-full flex justify-between items-center text-[11px] py-1 hover:text-[#e96a25] transition-colors"
                                        :class="filterState.category === deepChild.slug ? 'font-bold text-[#e96a25]' : 'text-gray-400'"
                                    >
                                        <span class="flex items-center gap-2">
                                            <!-- Level 3 tree branch indicator -->
                                            <span class="text-gray-300 font-normal">└─</span>
                                            <span>{{ deepChild.name }}</span>
                                        </span>
                                        <span class="text-[9px] font-bold text-gray-300" v-if="deepChild.products_count > 0">{{ deepChild.products_count }}</span>
                                    </button>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>

            <!-- Price Range -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wider">Price Range</h3>
                <span class="text-xs font-semibold text-[#e96a25]">{{ formatCurrency(filterState.max_price) }}</span>
            </div>
            <div class="mb-8">
                <input type="range" 
                       min="0" :max="Math.ceil(priceRange.max / 1000) * 1000" 
                       step="1000"
                       v-model.number="filterState.max_price"
                       class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#dcae96]">
            </div>

            <!-- Brands -->
            <h3 class="font-bold text-gray-900 mb-4 text-sm uppercase tracking-wider">Brand</h3>
            <ul class="space-y-3">
                <li v-for="brand in brands" :key="brand.brand" class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" :value="brand.brand" v-model="filterState.brands" class="h-4 w-4 text-[#dcae96] focus:ring-[#e96a25] border-gray-300 rounded mr-3">
                    <span>{{ brand.brand }}</span>
                    <span class="ml-auto text-[#e96a25] text-[10px] font-bold bg-[#fcece3] px-2 py-0.5 rounded-full">{{ brand.count }}</span>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-grow">
            
            <!-- Top Action Bar -->
            <div class="flex flex-wrap items-center justify-between mb-6">
                <!-- Applied Filters -->
                <div class="flex items-center gap-2 mb-2 sm:mb-0">
                    <span v-if="filterState.category" class="bg-gray-100 text-xs px-3 py-1.5 rounded-full flex items-center text-gray-700">
                        Category: <span class="font-bold ml-1">{{ filterState.category }}</span>
                        <button @click="setCategory(null)" class="ml-2 text-gray-500 hover:text-gray-800">&times;</button>
                    </span>
                    <span v-if="filterState.q" class="bg-gray-100 text-xs px-3 py-1.5 rounded-full flex items-center text-gray-700">
                        Search: <span class="font-bold ml-1">{{ filterState.q }}</span>
                        <button @click="filterState.q = ''" class="ml-2 text-gray-500 hover:text-gray-800">&times;</button>
                    </span>
                    <span v-if="filterState.brands.length" class="bg-gray-100 text-xs px-3 py-1.5 rounded-full flex items-center text-gray-700">
                        Brands: <span class="font-bold ml-1">{{ filterState.brands.join(', ') }}</span>
                        <button @click="filterState.brands = []" class="ml-2 text-gray-500 hover:text-gray-800">&times;</button>
                    </span>
                </div>

                <div class="flex items-center space-x-4 ml-auto">
                    <!-- Sort By -->
                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                        <span>Sort by:</span>
                        <select v-model="filterState.sort" class="border border-gray-200 rounded py-1.5 pl-3 pr-8 focus:ring-[#e96a25] focus:border-[#e96a25] text-sm font-medium text-gray-800 bg-white">
                            <option value="relevance">Relevance</option>
                            <option value="price_asc">Price Low-High</option>
                            <option value="price_desc">Price High-Low</option>
                        </select>
                    </div>

                    <!-- Grid/List View Toggles -->
                    <div class="flex border border-gray-200 rounded overflow-hidden">
                        <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-[#1a2b4c] text-white' : 'bg-white text-gray-500 hover:bg-gray-50'" class="p-1.5 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </button>
                        <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-[#1a2b4c] text-white' : 'bg-white text-gray-500 hover:bg-gray-50'" class="p-1.5 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Interactive Product Catalog Container with Loading Glassmorphism Overlay -->
            <div class="relative min-h-[400px]">
                
                <!-- Single Page Application Glassmorphism Loading State -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="isLoading" class="absolute inset-0 z-30 bg-white/40 backdrop-blur-[1.5px] flex flex-col items-center justify-start pt-32 transition-all duration-300 rounded-xl">
                        <div class="flex flex-col items-center space-y-3 bg-white shadow-2xl rounded-2xl px-8 py-6 border border-gray-100">
                            <div class="relative flex items-center justify-center">
                                <div class="w-12 h-12 border-4 border-[#e96a25]/20 border-t-[#e96a25] rounded-full animate-spin"></div>
                                <svg class="w-5 h-5 text-[#e96a25] absolute" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-800 tracking-wide">Updating Catalogue...</p>
                        </div>
                    </div>
                </Transition>

                <!-- Product Grid -->
                <TransitionGroup 
                    name="list" 
                    tag="div" 
                    :class="[
                        viewMode === 'grid' ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 relative' : 'flex flex-col space-y-3 sm:space-y-4 relative',
                        isLoading ? 'opacity-40 saturate-50 transition-all duration-500 select-none pointer-events-none' : 'transition-all duration-300'
                    ]"
                >
                <Link v-for="product in products" :key="product.id" :href="route('catalog.show', product.id)"
                     :class="viewMode === 'grid' ? 'flex-col p-3 sm:p-4' : 'flex-row items-start sm:items-center p-3 sm:p-6'"
                     class="bg-white border border-gray-100 rounded-xl flex hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden group">
                    
                    <!-- Added to cart overlay animation -->
                    <div v-if="animatingProduct === product.id" class="absolute inset-0 bg-white/90 z-10 flex flex-col items-center justify-center animate-pulse">
                        <svg class="w-8 h-8 sm:w-12 sm:h-12 text-[#1a2b4c] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span class="font-bold text-xs sm:text-base text-[#1a2b4c]">Adding...</span>
                    </div>

                    <!-- Image -->
                    <div :class="viewMode === 'grid' ? 'w-full mb-2 sm:mb-4' : 'w-24 sm:w-48 mr-3 sm:mr-6 flex-shrink-0'" class="aspect-w-1 aspect-h-1 bg-white flex items-center justify-center rounded-lg">
                        <img :src="product.image || generatePlaceholder(product.id)" :alt="product.name" class="object-contain mx-auto group-hover:scale-105 transition-transform duration-500" :class="viewMode === 'grid' ? 'max-h-28 sm:max-h-48' : 'max-h-24 sm:max-h-48'">
                    </div>

                    <!-- Details -->
                    <div class="flex-grow flex flex-col min-w-0" :class="viewMode === 'list' ? 'justify-center py-1 sm:py-2' : ''">
                        <h4 class="text-sm sm:text-base font-bold text-gray-900 leading-tight mb-1 truncate">{{ product.name }}</h4>
                        <div class="text-[10px] sm:text-xs text-gray-500 mb-1 sm:mb-2 flex flex-wrap items-center gap-x-1.5" :class="viewMode === 'grid' ? 'hidden sm:flex' : ''">
                            <span class="font-mono">{{ product.sku }}</span>
                            <span class="text-gray-300 hidden sm:inline">|</span>
                            <span class="hidden sm:inline">{{ product.brand || 'No Brand' }}</span>
                            <template v-if="product.uom">
                                <span class="text-gray-300 hidden sm:inline">|</span>
                                <span class="text-[#e96a25] font-semibold">UOM: {{ product.uom }}</span>
                            </template>
                            <template v-if="product.minimum_order > 1">
                                <span class="text-gray-300 hidden sm:inline">|</span>
                                <span class="text-red-600 font-bold">Min: {{ product.minimum_order }}</span>
                            </template>
                        </div>
                        
                        <!-- Description -->
                        <div class="hidden sm:block text-xs text-gray-600 mb-3" 
                             style="display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden;"
                             :style="{ '-webkit-line-clamp': viewMode === 'grid' ? 2 : 3 }"
                             :title="product.description">
                            {{ product.description || 'No description available.' }}
                        </div>
                        
                        <div class="mt-auto" :class="viewMode === 'list' ? 'flex flex-col sm:flex-row sm:items-center justify-between sm:mt-4' : ''">
                            <div class="flex items-end justify-between mb-2 sm:mb-4">
                                <span class="text-sm sm:text-xl font-extrabold text-gray-900">{{ formatCurrency(product.base_price) }}</span>
                            </div>
                            
                            <!-- Quantity and Add Button Container -->
                            <div v-if="canAddToCart" class="flex items-center space-x-1 sm:space-x-2" :class="viewMode === 'list' ? 'w-full sm:w-64 mt-1 sm:mt-0' : 'w-full'">
                                <!-- Quantity Selector -->
                                <div class="flex items-center border border-gray-300 rounded h-8 sm:h-10 w-20 sm:w-24 flex-shrink-0 bg-white hover:border-[#e96a25] transition-colors">
                                    <button @click.prevent="updateQuantity(product.id, -1)" class="px-1.5 sm:px-2 text-gray-500 hover:text-gray-900 transition-colors w-1/3 text-xs sm:text-base">-</button>
                                    <input type="number" @click.prevent v-model.number="quantities[product.id]" :min="Math.max(1, product.minimum_order || 1)" @change="validateQuantity(product)" class="w-1/3 text-center text-xs sm:text-sm border-none focus:ring-0 p-0 font-medium" />
                                    <button @click.prevent="updateQuantity(product.id, 1)" class="px-1.5 sm:px-2 text-gray-500 hover:text-gray-900 transition-colors w-1/3 text-xs sm:text-base">+</button>
                                </div>

                                <!-- Add to Cart Button -->
                                <button 
                                    @click.prevent="addToCart(product)"
                                    :disabled="form.processing && form.product_id === product.id"
                                    class="flex-grow h-8 sm:h-10 flex items-center justify-center rounded text-white transition-all duration-300"
                                    :class="addedProduct === product.id ? 'bg-green-600 hover:bg-green-700 shadow-md shadow-green-600/20' : 'bg-[#1a2b4c] hover:bg-[#121f38] hover:shadow-lg hover:shadow-[#1a2b4c]/30'"
                                    :title="addedProduct === product.id ? 'Added' : 'Quick Add'"
                                >
                                    <svg v-if="addedProduct === product.id" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <svg v-else class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </Link>
            </TransitionGroup>

            <div v-if="products.length === 0" class="text-center py-24 text-gray-500 border border-dashed border-gray-300 rounded-xl mt-4">
                <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-lg font-medium">No products match your filters.</p>
                <button @click="filterState.brands = []; filterState.category = null; filterState.q = ''" class="mt-4 text-[#e96a25] hover:underline font-medium">Clear all filters</button>
            </div>

            </div> <!-- END Interactive Product Catalog Container -->

        </div>
    </StoreLayout>
</template>
