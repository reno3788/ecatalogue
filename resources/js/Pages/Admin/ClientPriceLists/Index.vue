<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategorySidebar from '@/Components/CategorySidebar.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    priceLists: Array,
    companies: Array,
    products: Array,
    categories: Array,
});

const searchQuery = ref('');
const isModalOpen = ref(false);
const isUploadModalOpen = ref(false);
const editingPriceId = ref(null);
const fileInputRef = ref(null);

// Forms
const addForm = useForm({
    company_id: '',
    product_id: '',
    custom_price: '',
});

const editForm = useForm({
    custom_price: '',
});

const uploadForm = useForm({
    file: null,
});

const handleFileChange = (e) => {
    uploadForm.file = e.target.files[0];
};

const triggerFileInput = () => {
    fileInputRef.value.click();
};

const submitUpload = () => {
    uploadForm.post(route('admin.client-price-lists.upload'), {
        onSuccess: () => {
            isUploadModalOpen.value = false;
            uploadForm.reset();
        }
    });
};

const displayAddPrice = ref('');
const displayEditPrice = ref('');

const formatNumberWithCommas = (value) => {
    if (!value && value !== 0) return '';
    let valStr = value.toString();
    
    // Split the input string into integer and fraction parts to ensure commas ONLY impact integers
    const parts = valStr.split('.');
    let integerPart = parts[0].replace(/[^0-9]/g, '');
    let formattedInt = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    
    // If a decimal dot was detected, safely restore fractional precision up to 2 units
    if (parts.length > 1) {
        let decimalPart = parts.slice(1).join('').replace(/[^0-9]/g, '').substring(0, 2);
        return `${formattedInt}.${decimalPart}`;
    }
    
    return formattedInt;
};

const parseCommasToNumber = (value) => {
    return value.toString().replace(/,/g, '');
};

const onAddPriceInput = (e) => {
    const formatted = formatNumberWithCommas(e.target.value);
    
    // Sync view representation explicitly
    e.target.value = formatted;
    displayAddPrice.value = formatted;
    
    // Pass cleaned mathematical string back to state form
    addForm.custom_price = parseCommasToNumber(formatted);
};

const onEditPriceInput = (e) => {
    const formatted = formatNumberWithCommas(e.target.value);
    
    // Sync view representation explicitly
    e.target.value = formatted;
    displayEditPrice.value = formatted;
    
    // Pass cleaned mathematical string back to state form
    editForm.custom_price = parseCommasToNumber(formatted);
};

// Computed search filter
const selectedCategoryId = ref(null);

const getCategoryDescendants = (catId) => {
    const descendants = [Number(catId)];
    const getChildren = (id) => {
        const children = props.categories.filter(c => Number(c.parent_id) === Number(id));
        children.forEach(child => {
            descendants.push(Number(child.id));
            getChildren(child.id);
        });
    };
    getChildren(catId);
    return descendants;
};

const filteredPriceLists = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    
    return props.priceLists.filter(item => {
        // 1. Filter by category (including descendants)
        if (selectedCategoryId.value !== null) {
            const targetIds = getCategoryDescendants(selectedCategoryId.value);
            const hasCategory = item.product?.categories?.some(cat => targetIds.includes(Number(cat.id)));
            if (!hasCategory) return false;
        }
        
        // 2. Filter by search query
        if (query) {
            const companyName = item.company?.name?.toLowerCase() || '';
            const productName = item.product?.name?.toLowerCase() || '';
            const sku = item.product?.sku?.toLowerCase() || '';
            return companyName.includes(query) || productName.includes(query) || sku.includes(query);
        }
        
        return true;
    });
});

const getCategoryCount = (categoryId) => {
    if (categoryId === null) return props.priceLists.length;
    const targetIds = getCategoryDescendants(categoryId);
    return props.priceLists.filter(item => 
        item.product?.categories?.some(cat => targetIds.includes(Number(cat.id)))
    ).length;
};


const selectedProductInfo = computed(() => {
    if (!addForm.product_id) return null;
    return props.products.find(p => Number(p.id) === Number(addForm.product_id));
});

const productSearchQuery = ref('');
const isProductDropdownOpen = ref(false);

const filteredSearchProducts = computed(() => {
    const query = productSearchQuery.value.toLowerCase().trim();
    if (!query) return props.products;
    return props.products.filter(p => {
        const name = p.name?.toLowerCase() || '';
        const sku = p.sku?.toLowerCase() || '';
        return name.includes(query) || sku.includes(query);
    });
});

const selectProduct = (product) => {
    addForm.product_id = product.id;
    productSearchQuery.value = `${product.name} (SKU: ${product.sku || 'N/A'})`;
    isProductDropdownOpen.value = false;
};

const onProductSearchInput = (e) => {
    productSearchQuery.value = e.target.value;
    isProductDropdownOpen.value = true;
    if (!e.target.value) {
        addForm.product_id = '';
    }
};

const onProductSearchFocus = () => {
    isProductDropdownOpen.value = true;
    if (!addForm.product_id) {
        productSearchQuery.value = '';
    }
};

// Actions
const openAddModal = () => {
    addForm.reset();
    displayAddPrice.value = '';
    productSearchQuery.value = '';
    isProductDropdownOpen.value = false;
    isModalOpen.value = true;
};

const closeAddModal = () => {
    isModalOpen.value = false;
    displayAddPrice.value = '';
    productSearchQuery.value = '';
    isProductDropdownOpen.value = false;
};

const submitAdd = () => {
    // Clear old errors
    addForm.clearErrors('custom_price');
    
    // Strict client-side validation
    const rawPrice = addForm.custom_price;
    if (!rawPrice || isNaN(rawPrice) || parseFloat(rawPrice) < 0) {
        addForm.setError('custom_price', 'The price field must be a valid numeric value.');
        return;
    }

    addForm.post(route('admin.client-price-lists.store'), {
        onSuccess: () => {
            closeAddModal();
            addForm.reset();
        }
    });
};

const startEdit = (item) => {
    editingPriceId.value = item.id;
    editForm.custom_price = item.custom_price;
    displayEditPrice.value = formatNumberWithCommas(item.custom_price);
};

const cancelEdit = () => {
    editingPriceId.value = null;
    displayEditPrice.value = '';
    editForm.reset();
};

const alertModal = ref({
    show: false,
    title: '',
    message: ''
});

const submitUpdate = (id) => {
    // Strict client-side validation
    const rawPrice = editForm.custom_price;
    if (!rawPrice || isNaN(rawPrice) || parseFloat(rawPrice) < 0) {
        alertModal.value = {
            show: true,
            title: 'Invalid Price Value',
            message: 'The price field must contain a valid positive numeric value.'
        };
        return;
    }

    editForm.put(route('admin.client-price-lists.update', id), {
        onSuccess: () => {
            editingPriceId.value = null;
        }
    });
};

const priceToDelete = ref(null);
const showDeleteModal = ref(false);

const deletePrice = (id) => {
    priceToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDeletePrice = () => {
    if (priceToDelete.value) {
        useForm({}).delete(route('admin.client-price-lists.destroy', priceToDelete.value), {
            onFinish: () => {
                showDeleteModal.value = false;
                priceToDelete.value = null;
            }
        });
    }
};

// Formatting helpers
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

const currencySymbol = computed(() => {
    const currency = page.props.appSettings?.currency || 'EUR';
    const formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: currency });
    const parts = formatter.formatToParts(0);
    const currencyPart = parts.find(p => p.type === 'currency');
    return currencyPart ? currencyPart.value : currency;
});

const getPriceDiffBadge = (item) => {
    const custom = parseFloat(item.custom_price);
    const base = parseFloat(item.product?.base_price);
    
    if (!base || !custom || base === 0) {
        return {
            text: '0%',
            classes: 'bg-gray-50 text-gray-500 border border-gray-100'
        };
    }
    
    const diffPercent = ((custom - base) / base) * 100;
    const rounded = Math.round(diffPercent);
    
    if (rounded < 0) {
        return {
            text: `${rounded}%`, // e.g., "-15%"
            classes: 'bg-emerald-50 text-emerald-600 border border-emerald-100'
        };
    } else if (rounded > 0) {
        return {
            text: `+${rounded}%`, // e.g., "+15%"
            classes: 'bg-amber-50 text-amber-600 border border-amber-100'
        };
    } else {
        return {
            text: '0%',
            classes: 'bg-gray-50 text-gray-500 border border-gray-100'
        };
    }
};
</script>

<template>
    <Head title="Client Price Lists" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Client Price Lists</h2>
                    <p class="text-sm text-gray-500 mt-1">Configure and manage client-specific custom prices for catalog products.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button 
                        @click="isUploadModalOpen = true"
                        class="inline-flex items-center space-x-2 bg-white border border-gray-200 text-[#1a2b4c] font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm hover:bg-gray-50 transition duration-200"
                    >
                        <svg class="w-4 h-4 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Upload Price List</span>
                    </button>
                    <button 
                        @click="openAddModal"
                        class="inline-flex items-center space-x-2 bg-gradient-to-r from-[#e96a25] to-[#d55e1d] text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg hover:from-[#d55e1d] hover:to-[#b84a14] transition duration-200"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Custom Price</span>
                    </button>
                </div>
            </div>
        </template>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-[#1a2b4c] to-[#0f1b33] rounded-2xl p-6 shadow-sm border border-white/5 text-white flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold tracking-widest text-gray-400 uppercase">Custom Prices Map</span>
                    <h3 class="text-3xl font-black mt-2 tracking-tight">{{ priceLists.length }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center text-[#e96a25]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2-2 4 4m0-7l-2-2-4-4m-2 1h18M3 21h18" />
                    </svg>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold tracking-widest text-gray-400 uppercase">Impacted Companies</span>
                    <h3 class="text-3xl font-black mt-2 tracking-tight text-[#1a2b4c]">
                        {{ new Set(priceLists.map(p => p.company_id)).size }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold tracking-widest text-gray-400 uppercase">Affected Products</span>
                    <h3 class="text-3xl font-black mt-2 tracking-tight text-[#e96a25]">
                        {{ new Set(priceLists.map(p => p.product_id)).size }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[#e96a25]/5 flex items-center justify-center text-[#e96a25]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Main Grid Layout: Categories Sidebar & Tables Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Sidebar -->
            <CategorySidebar
                :categories="categories"
                :getCategoryCount="getCategoryCount"
                v-model:selectedCategoryId="selectedCategoryId"
            />

            <!-- Content Area: Search and Table -->
            <div class="lg:col-span-9 space-y-6">
                <!-- Filter and Search -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <div class="relative max-w-md">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            placeholder="Search by company name, product name..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 text-sm"
                    </div>
                </div>

                <!-- CSV Skip Row Error Alerts -->
                <div v-if="$page.props.flash?.import_errors && $page.props.flash.import_errors.length > 0" class="bg-amber-50 rounded-2xl p-5 border border-amber-100 mb-6">
                    <div class="flex items-start space-x-3 text-amber-700">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="flex-grow">
                            <h4 class="font-bold text-sm text-amber-900">Import Parsing Report: Some rows were skipped</h4>
                            <p class="text-xs text-amber-800 mt-1">The system successfully processed valid prices, but skipped the following lines due to missing or invalid identifiers:</p>
                            <div class="mt-3 max-h-40 overflow-y-auto space-y-1.5 pr-2">
                                <p 
                                    v-for="(err, idx) in $page.props.flash.import_errors" 
                                    :key="idx"
                                    class="text-[11px] bg-white border border-amber-100 rounded-lg px-3 py-1.5 font-semibold font-mono text-amber-900"
                                >
                                    {{ err }}
                                </p>
                            </div>
                        </div>
                        <button 
                            @click="$page.props.flash.import_errors = null"
                            class="text-amber-400 hover:text-amber-600 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Table View -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                    <div class="overflow-x-auto border border-gray-100 rounded-xl">
                        <table class="min-w-full divide-y divide-gray-100 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Company</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Product Info</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Catalog Price</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Custom Price</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                <tr v-if="filteredPriceLists.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No custom client prices found matching your selection.
                                    </td>
                                </tr>
                                <tr v-for="item in filteredPriceLists" :key="item.id" class="hover:bg-gray-50/50 transition-colors">
                                    <!-- Company -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-[#1a2b4c] font-black text-xs uppercase">
                                                {{ item.company?.name?.substring(0, 2) || 'CO' }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-sm">{{ item.company?.name }}</h4>
                                                <p v-if="item.company?.code" class="text-xs text-gray-500">{{ item.company.code }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Product -->
                                    <td class="px-6 py-4">
                                        <div>
                                            <h4 class="font-bold text-gray-900 text-sm">{{ item.product?.name }}</h4>
                                            <p class="text-xs text-gray-500">SKU: {{ item.product?.sku || 'N/A' }}</p>
                                        </div>
                                    </td>

                                    <!-- Standard Price -->
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-500 font-medium">
                                            {{ formatCurrency(item.product?.base_price || 0) }}
                                        </span>
                                    </td>

                                    <!-- Custom Price -->
                                    <td class="px-6 py-4">
                                        <div v-if="editingPriceId === item.id" class="flex items-center space-x-2">
                                            <div class="relative rounded-md shadow-sm max-w-[120px]">
                                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
                                                    <span class="text-gray-500 text-xs">{{ currencySymbol }}</span>
                                                </div>
                                                <input 
                                                    type="text" 
                                                    :value="displayEditPrice" 
                                                    @input="onEditPriceInput"
                                                    placeholder="0.00"
                                                    class="w-full pl-8 pr-2 py-1 text-xs rounded-md border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 font-bold"
                                                />
                                            </div>
                                            <button 
                                                @click="submitUpdate(item.id)"
                                                class="p-1.5 bg-[#e96a25] text-white rounded-md hover:bg-[#d55e1d]"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            <button 
                                                @click="cancelEdit"
                                                class="p-1.5 bg-gray-100 text-gray-600 rounded-md hover:bg-gray-200"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div v-else class="flex items-center space-x-2">
                                            <span class="text-sm font-bold text-[#e96a25]">
                                                {{ formatCurrency(item.custom_price) }}
                                            </span>
                                            <span :class="['text-[10px] px-1.5 py-0.5 rounded-full font-bold', getPriceDiffBadge(item).classes]">
                                                {{ getPriceDiffBadge(item).text }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button 
                                                v-if="editingPriceId !== item.id"
                                                @click="startEdit(item)"
                                                class="p-1.5 text-gray-400 hover:text-[#1a2b4c] transition"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button 
                                                @click="deletePrice(item.id)"
                                                class="p-1.5 text-gray-400 hover:text-red-500 transition"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Custom Price Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div @click="closeAddModal" class="fixed inset-0 bg-[#1a2b4c]/40 backdrop-blur-sm transition-opacity"></div>

            <!-- Modal Content -->
            <div class="relative bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl border border-gray-100 transition-transform scale-100">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-bold text-lg text-[#1a2b4c]">Add Custom Client Price</h3>
                    <button @click="closeAddModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitAdd" class="space-y-4">
                    <!-- Company Dropdown -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Select Client / Company</label>
                        <select 
                            v-model="addForm.company_id" 
                            required
                            class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5"
                        >
                            <option value="" disabled>-- Select Company --</option>
                            <option v-for="company in companies" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                        <span v-if="addForm.errors.company_id" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.company_id }}</span>
                    </div>

                    <!-- Searchable Product Dropdown -->
                    <div class="relative">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Select Product</label>
                        <div class="relative">
                            <input 
                                type="text"
                                :value="productSearchQuery"
                                @input="onProductSearchInput"
                                @focus="onProductSearchFocus"
                                @blur="setTimeout(() => isProductDropdownOpen = false, 250)"
                                placeholder="Search by product name or SKU..."
                                class="w-full text-sm rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-2.5 pl-3.5 pr-10 font-semibold text-gray-800"
                                required
                            />
                            <!-- Dropdown indicator chevron -->
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Floating dropdown list of options -->
                        <div v-if="isProductDropdownOpen && filteredSearchProducts.length > 0" class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-xl divide-y divide-gray-50">
                            <button
                                v-for="product in filteredSearchProducts"
                                :key="product.id"
                                type="button"
                                @mousedown="selectProduct(product)"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 transition flex items-center justify-between"
                            >
                                <div class="pr-4">
                                    <h5 class="font-bold text-gray-900 text-sm">{{ product.name }}</h5>
                                    <p class="text-xs text-gray-400 mt-0.5 font-medium">SKU: {{ product.sku || 'N/A' }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-xs bg-[#e96a25]/10 text-[#e96a25] px-2.5 py-1 rounded-lg font-bold">
                                        {{ formatCurrency(product.base_price) }}
                                    </span>
                                </div>
                            </button>
                        </div>
                        <div v-else-if="isProductDropdownOpen" class="absolute z-50 mt-1 w-full bg-white border border-gray-100 rounded-xl shadow-xl p-4 text-center text-sm text-gray-500">
                            No products found matching "{{ productSearchQuery }}"
                        </div>

                        <!-- Selected Product Price Information Box -->
                        <div v-if="selectedProductInfo" class="mt-2 flex items-center space-x-2 bg-blue-50/70 text-blue-700 px-3 py-2.5 rounded-xl text-xs font-semibold border border-blue-100/50">
                            <svg class="w-4 h-4 flex-shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Standard Catalog Price: <strong class="text-sm font-bold ml-1 text-blue-900">{{ formatCurrency(selectedProductInfo.base_price) }}</strong></span>
                        </div>

                        <span v-if="addForm.errors.product_id" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.product_id }}</span>
                    </div>

                    <!-- Custom Price -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Custom Price ({{ currencySymbol }})</label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <span class="text-gray-500 text-sm">{{ currencySymbol }}</span>
                            </div>
                            <input 
                                type="text" 
                                :value="displayAddPrice" 
                                @input="onAddPriceInput"
                                required
                                placeholder="0.00"
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 text-sm font-bold"
                            />
                        </div>
                        <span v-if="addForm.errors.custom_price" class="text-xs text-red-500 mt-1 block">{{ addForm.errors.custom_price }}</span>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="closeAddModal"
                            class="px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 rounded-xl transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="addForm.processing"
                            class="px-5 py-2.5 text-sm font-bold bg-gradient-to-r from-[#e96a25] to-[#d55e1d] text-white rounded-xl shadow-md hover:shadow-lg hover:from-[#d55e1d] hover:to-[#b84a14] transition disabled:opacity-50"
                        >
                            Save Price
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upload Custom Price List Modal -->
        <div v-if="isUploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div @click="isUploadModalOpen = false" class="fixed inset-0 bg-[#1a2b4c]/40 backdrop-blur-sm transition-opacity"></div>

            <!-- Modal Content -->
            <div class="relative bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl border border-gray-100 transition-transform scale-100">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-lg text-[#1a2b4c]">Upload Client Price List</h3>
                    <button @click="isUploadModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mb-5 bg-[#e96a25]/5 rounded-xl p-4 border border-[#e96a25]/10 text-xs text-[#e96a25] leading-relaxed font-medium">
                    <p class="font-bold flex items-center space-x-1.5 mb-1.5">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>How bulk pricing import works:</span>
                    </p>
                    <ul class="list-disc pl-4 space-y-1 mt-1 text-gray-600">
                        <li>The system will match rows by <strong class="text-gray-800">product_sku</strong> and either <strong class="text-gray-800">company_id</strong> or <strong class="text-gray-800">company_name</strong>.</li>
                        <li>Existing custom prices will be updated; new custom prices will be created.</li>
                        <li>Faulty or non-matching rows will be skipped gracefully and listed in a detailed report.</li>
                    </ul>
                    <div class="mt-3">
                        <a 
                            :href="route('admin.client-price-lists.template')"
                            class="inline-flex items-center space-x-1 font-bold text-[#e96a25] hover:underline"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4-4v12" />
                            </svg>
                            <span>Download Pre-formatted CSV Template</span>
                        </a>
                    </div>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-4">
                    <!-- Dropzone -->
                    <div 
                        @click="triggerFileInput"
                        class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition hover:bg-gray-50/50 flex flex-col items-center justify-center"
                        :class="uploadForm.file ? 'border-[#e96a25] bg-[#e96a25]/5' : 'border-gray-200'"
                    >
                        <input 
                            type="file" 
                            ref="fileInputRef" 
                            @change="handleFileChange" 
                            accept=".csv" 
                            class="hidden" 
                            required
                        />

                        <div v-if="uploadForm.file" class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-[#e96a25]/10 text-[#e96a25] rounded-xl flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h4 class="font-bold text-sm text-gray-800">{{ uploadForm.file.name }}</h4>
                            <p class="text-xs text-gray-400 mt-1 font-medium">{{ (uploadForm.file.size / 1024).toFixed(1) }} KB</p>
                            <span class="text-xs text-[#e96a25] hover:underline mt-2 font-bold">Change file</span>
                        </div>
                        <div v-else class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <h4 class="font-bold text-sm text-gray-700">Drag & drop your CSV file here</h4>
                            <p class="text-xs text-gray-400 mt-1 font-medium">or click to browse from files (.csv)</p>
                        </div>
                    </div>

                    <span v-if="uploadForm.errors.file" class="text-xs text-red-500 block mt-1">{{ uploadForm.errors.file }}</span>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="isUploadModalOpen = false"
                            class="px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 rounded-xl transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="uploadForm.processing || !uploadForm.file"
                            class="px-5 py-2.5 text-sm font-bold bg-gradient-to-r from-[#e96a25] to-[#d55e1d] text-white rounded-xl shadow-md hover:shadow-lg hover:from-[#d55e1d] hover:to-[#b84a14] transition disabled:opacity-50"
                        >
                            Upload & Parse
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Delete Custom Price Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Remove Custom Price"
            message="Are you sure you want to remove this custom client price? This action will revert the product pricing for this client back to standard catalog rates."
            type="danger"
            confirmLabel="Confirm Removal"
            @close="showDeleteModal = false; priceToDelete = null"
            @confirm="executeDeletePrice"
        />

        <!-- Input Validation Warning Modal -->
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
    </AuthenticatedLayout>
</template>
