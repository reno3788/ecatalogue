<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategorySidebar from '@/Components/CategorySidebar.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    products: Array,
    categories: Array,
});

const page = usePage();
const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val); }
    catch (e) { return `${currency} ${Number(val).toFixed(2)}`; }
};

const searchQuery = ref('');
const selectedCategoryId = ref(null);
const isUploadModalOpen = ref(false);
const fileInputRef = ref(null);

const uploadForm = useForm({ file: null });
const handleFileChange = (e) => { uploadForm.file = e.target.files[0]; };
const triggerFileInput = () => fileInputRef.value.click();
const submitUpload = () => {
    uploadForm.post(route('admin.products.upload'), {
        onSuccess: () => { isUploadModalOpen.value = false; uploadForm.reset(); }
    });
};

// Category descendant helper (used by getCategoryCount and filteredProducts)
const getCategoryDescendants = (catId) => {
    const desc = [Number(catId)];
    const getChildren = (id) => {
        props.categories.filter(c => Number(c.parent_id) === Number(id)).forEach(child => {
            desc.push(Number(child.id));
            getChildren(child.id);
        });
    };
    getChildren(catId);
    return desc;
};

// Passed as prop to CategorySidebar
const getCategoryCount = (catId) => {
    if (catId === null) return props.products.length;
    const ids = getCategoryDescendants(catId);
    return props.products.filter(p => p.categories?.some(c => ids.includes(Number(c.id)))).length;
};

const filteredProducts = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    return props.products.filter(p => {
        if (selectedCategoryId.value !== null) {
            const ids = getCategoryDescendants(selectedCategoryId.value);
            if (!p.categories?.some(c => ids.includes(Number(c.id)))) return false;
        }
        if (q) {
            return (p.name?.toLowerCase().includes(q) || p.sku?.toLowerCase().includes(q) || p.brand?.toLowerCase().includes(q));
        }
        return true;
    });
});

const productToDelete = ref(null);
const showDeleteModal = ref(false);

const deleteProduct = (id) => {
    productToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDeleteProduct = () => {
    if (productToDelete.value) {
        useForm({}).delete(route('admin.products.destroy', productToDelete.value), {
            onFinish: () => {
                showDeleteModal.value = false;
                productToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <Head title="Manage Products" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Manage Products</h2>
                    <p class="text-sm text-gray-500 mt-1">Browse, filter, and manage your product catalog.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button @click="isUploadModalOpen = true"
                        class="inline-flex items-center space-x-2 bg-white border border-gray-200 text-[#1a2b4c] font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm hover:bg-gray-50 transition duration-200">
                        <svg class="w-4 h-4 text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Upload CSV</span>
                    </button>
                    <Link :href="route('admin.products.create')"
                        class="inline-flex items-center space-x-2 bg-gradient-to-r from-[#e96a25] to-[#d55e1d] text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg hover:from-[#d55e1d] hover:to-[#b84a14] transition duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Product</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Flash success -->
                <div v-if="$page.props.flash?.success" class="mb-6 bg-green-50 border border-green-100 rounded-2xl px-5 py-3 text-sm text-green-700 font-semibold flex items-center space-x-2">
                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                    <!-- Shared Category Sidebar -->
                    <CategorySidebar
                        :categories="categories"
                        :getCategoryCount="getCategoryCount"
                        v-model:selectedCategoryId="selectedCategoryId"
                    />

                    <!-- Main Content -->
                    <div class="lg:col-span-9 space-y-6">

                        <!-- Import error report -->
                        <div v-if="$page.props.flash?.import_errors?.length" class="bg-amber-50 rounded-2xl p-5 border border-amber-100">
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <div class="flex-grow">
                                    <h4 class="font-bold text-sm text-amber-900">Import Report: Some rows were skipped</h4>
                                    <div class="mt-2 max-h-40 overflow-y-auto space-y-1">
                                        <p v-for="(err, i) in $page.props.flash.import_errors" :key="i"
                                            class="text-[11px] bg-white border border-amber-100 rounded-lg px-3 py-1.5 font-mono font-semibold text-amber-900">{{ err }}</p>
                                    </div>
                                </div>
                                <button @click="$page.props.flash.import_errors = null" class="text-amber-400 hover:text-amber-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                            <div class="relative max-w-md">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </span>
                                <input type="text" v-model="searchQuery" placeholder="Search by name, SKU, or brand..."
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 text-sm" />
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                                <table class="min-w-full divide-y divide-gray-100 text-left">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-5 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">SKU</th>
                                            <th class="px-5 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Product</th>
                                            <th class="px-5 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Base Price</th>
                                            <th class="px-5 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Brand</th>
                                            <th class="px-5 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Categories</th>
                                            <th class="px-5 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="product in filteredProducts" :key="product.id" class="hover:bg-gray-50/60 transition">
                                            <td class="px-5 py-4 text-xs font-mono font-bold text-gray-400">{{ product.sku }}</td>
                                            <td class="px-5 py-4">
                                                <div class="flex items-center space-x-3">
                                                    <img v-if="product.image" :src="product.image" class="w-9 h-9 rounded-lg object-cover border border-gray-100 flex-shrink-0" />
                                                    <div v-else class="w-9 h-9 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    </div>
                                                    <span class="font-semibold text-sm text-gray-800">{{ product.name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 text-sm font-bold text-[#e96a25]">{{ formatCurrency(product.base_price) }}</td>
                                            <td class="px-5 py-4 text-sm text-gray-500">{{ product.brand || '—' }}</td>
                                            <td class="px-5 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <span v-for="cat in product.categories" :key="cat.id" class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-bold rounded-full">{{ cat.name }}</span>
                                                    <span v-if="!product.categories?.length" class="text-gray-300 text-xs">—</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <Link 
                                                        :href="route('admin.products.edit', product.id)"
                                                        class="p-1.5 text-gray-400 hover:text-[#1a2b4c] transition"
                                                        title="Edit Product"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                        </svg>
                                                    </Link>
                                                    <button 
                                                        @click="deleteProduct(product.id)"
                                                        class="p-1.5 text-gray-400 hover:text-red-500 transition"
                                                        title="Delete Product"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredProducts.length === 0">
                                            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">No products found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-xs text-gray-400 mt-3 font-medium">Showing {{ filteredProducts.length }} of {{ products.length }} products</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <div v-if="isUploadModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div @click="isUploadModalOpen = false" class="fixed inset-0 bg-[#1a2b4c]/40 backdrop-blur-sm"></div>
            <div class="relative bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl border border-gray-100">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-lg text-[#1a2b4c]">Bulk Import Products</h3>
                    <button @click="isUploadModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="mb-5 bg-[#e96a25]/5 rounded-xl p-4 border border-[#e96a25]/10 text-xs leading-relaxed">
                    <p class="font-bold text-[#e96a25] mb-1.5">CSV columns: <code class="font-mono">name, sku, base_price, brand, description, categories, weight, color, size</code></p>
                    <ul class="list-disc pl-4 space-y-1 text-gray-600">
                        <li>Existing SKU → updates the product</li>
                        <li>New SKU → creates the product</li>
                        <li><strong>categories</strong>: pipe-separated category names, e.g. <code class="font-mono">Electronics|Laptops</code></li>
                        <li><strong>weight</strong>: numeric weight in kg, e.g. <code class="font-mono">1.5</code></li>
                        <li><strong>color</strong>: color hex code, e.g. <code class="font-mono">#FF0000</code></li>
                        <li><strong>size</strong>: text describing product size, e.g. <code class="font-mono">XL</code></li>
                        <li>Bad rows are skipped and reported</li>
                    </ul>
                    <a :href="route('admin.products.template')" class="inline-flex items-center space-x-1 font-bold text-[#e96a25] hover:underline mt-3">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4-4v12" /></svg>
                        <span>Download CSV Template</span>
                    </a>
                </div>
                <form @submit.prevent="submitUpload" class="space-y-4">
                    <div @click="triggerFileInput"
                        class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition hover:bg-gray-50/50 flex flex-col items-center"
                        :class="uploadForm.file ? 'border-[#e96a25] bg-[#e96a25]/5' : 'border-gray-200'">
                        <input type="file" ref="fileInputRef" @change="handleFileChange" accept=".csv" class="hidden" />
                        <div v-if="uploadForm.file" class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-[#e96a25]/10 text-[#e96a25] rounded-xl flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <h4 class="font-bold text-sm text-gray-800">{{ uploadForm.file.name }}</h4>
                            <p class="text-xs text-gray-400 mt-1">{{ (uploadForm.file.size / 1024).toFixed(1) }} KB</p>
                        </div>
                        <div v-else class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                            </div>
                            <h4 class="font-bold text-sm text-gray-700">Click to browse CSV file</h4>
                            <p class="text-xs text-gray-400 mt-1">Only .csv files accepted</p>
                        </div>
                    </div>
                    <span v-if="uploadForm.errors.file" class="text-xs text-red-500 block">{{ uploadForm.errors.file }}</span>
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="isUploadModalOpen = false" class="px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 rounded-xl transition">Cancel</button>
                        <button type="submit" :disabled="uploadForm.processing || !uploadForm.file"
                            class="px-5 py-2.5 text-sm font-bold bg-gradient-to-r from-[#e96a25] to-[#d55e1d] text-white rounded-xl shadow-md hover:shadow-lg hover:from-[#d55e1d] hover:to-[#b84a14] transition disabled:opacity-50">
                            Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Delete Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Delete Product"
            message="Are you sure you want to permanently delete this product? This action cannot be undone."
            type="danger"
            confirmLabel="Delete Product"
            @close="showDeleteModal = false; productToDelete = null"
            @confirm="executeDeleteProduct"
        />
    </AuthenticatedLayout>
</template>
