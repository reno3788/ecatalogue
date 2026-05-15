<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';

const props = defineProps({
    product: {
        type: Object,
        default: () => ({
            name: '',
            sku: '',
            base_price: '',
            minimum_order: 1,
            brand: '',
            weight: '',
            color: '#4f46e5',
            size: '',
            description: '',
            uom: '',
            classification: '',
            manufacturer_part_id: '',
            manufacturer_name: '',
            categories: []
        })
    },
    categories: Array,
});

const isEditing = !!props.product.id;

const form = useForm({
    name: props.product.name,
    sku: props.product.sku,
    base_price: props.product.base_price,
    minimum_order: props.product.minimum_order || 1,
    brand: props.product.brand || '',
    weight: props.product.weight || '',
    color: props.product.color || '#4f46e5',
    size: props.product.size || '',
    description: props.product.description || '',
    uom: props.product.uom || '',
    classification: props.product.classification || '',
    manufacturer_part_id: props.product.manufacturer_part_id || '',
    manufacturer_name: props.product.manufacturer_name || '',
    categories: props.product.categories ? props.product.categories.map(c => c.id) : [],
    images: [],
    primary_image_index: 0
});

const allImages = ref([]);

const handleImageUpload = (e) => {
    const files = Array.from(e.target.files);
    
    files.forEach(file => {
        form.images.push(file);
        const index = form.images.length - 1;
        
        const reader = new FileReader();
        reader.onload = (ev) => {
            allImages.value.push({
                type: 'new',
                index: index,
                url: ev.target.result,
                isPrimary: allImages.value.length === 0
            });
            updatePrimaryState();
        };
        reader.readAsDataURL(file);
    });
};

const removeImage = (img) => {
    form.images.splice(img.index, 1);
    allImages.value.forEach(i => {
        if (i.index > img.index) {
            i.index--;
        }
    });
    
    const idx = allImages.value.indexOf(img);
    if (idx > -1) {
        allImages.value.splice(idx, 1);
    }
    
    if (img.isPrimary && allImages.value.length > 0) {
        setPrimary(allImages.value[0]);
    } else {
        updatePrimaryState();
    }
};

const setPrimary = (img) => {
    allImages.value.forEach(i => i.isPrimary = false);
    img.isPrimary = true;
    updatePrimaryState();
};

const updatePrimaryState = () => {
    const primaryImg = allImages.value.find(i => i.isPrimary);
    if (primaryImg) {
        form.primary_image_index = primaryImg.index;
    } else {
        form.primary_image_index = 0;
    }
};

const submit = () => {
    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.products.update', props.product.id));
    } else {
        form.post(route('admin.products.store'));
    }
};

const breadcrumbItems = [
    { label: 'Dashboard', href: route('admin.dashboard'), icon: 'dashboard' },
    { label: 'Products', href: route('admin.products.index') },
];
</script>

<template>
    <Head :title="isEditing ? 'Edit Product' : 'Add Product'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <h2 class="font-extrabold text-2xl text-gray-900 leading-tight">
                    {{ isEditing ? 'Edit Product' : 'Add Product' }}
                </h2>
                <Breadcrumbs :items="breadcrumbItems" />
            </div>
        </template>

        <div class="py-1">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SKU</label>
                            <input v-model="form.sku" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]" required>
                            <div v-if="form.errors.sku" class="text-red-500 text-xs mt-1">{{ form.errors.sku }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]" required>
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>
                       <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="form.description" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]"></textarea>
                            <div v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Base Price</label>
                            <input v-model="form.base_price" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]" required>
                            <div v-if="form.errors.base_price" class="text-red-500 text-xs mt-1">{{ form.errors.base_price }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Minimum Order Quantity</label>
                            <input v-model="form.minimum_order" type="number" min="1" step="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]" required>
                            <div v-if="form.errors.minimum_order" class="text-red-500 text-xs mt-1">{{ form.errors.minimum_order }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Brand</label>
                            <input v-model="form.brand" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                            <div v-if="form.errors.brand" class="text-red-500 text-xs mt-1">{{ form.errors.brand }}</div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                                <input v-model="form.weight" type="number" step="0.01" placeholder="e.g. 1.5" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                <div v-if="form.errors.weight" class="text-red-500 text-xs mt-1">{{ form.errors.weight }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Size</label>
                                <input v-model="form.size" type="text" placeholder="e.g. L, XL, 15-inch" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                <div v-if="form.errors.size" class="text-red-500 text-xs mt-1">{{ form.errors.size }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Color</label>
                                <div class="mt-1 flex items-center space-x-2">
                                    <div class="relative w-10 h-10 rounded-lg overflow-hidden border border-gray-300 shadow-sm flex-shrink-0 cursor-pointer">
                                        <input v-model="form.color" type="color" class="absolute inset-0 w-full h-full p-0 border-0 cursor-pointer scale-150">
                                    </div>
                                    <input v-model="form.color" type="text" placeholder="#000000" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25] uppercase text-sm font-mono">
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">Select a color or enter its hex code.</p>
                                <div v-if="form.errors.color" class="text-red-500 text-xs mt-1">{{ form.errors.color }}</div>
                            </div>
                        </div>

                        <!-- B2B Procurement Metadata -->
                        <div class="border-t border-gray-100 pt-4 pb-2">
                            <h3 class="text-xs font-bold text-gray-500 mb-3 uppercase tracking-wider">B2B Procurement Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Unit of Measure (UOM)</label>
                                    <input v-model="form.uom" type="text" placeholder="e.g. EA, CS, UN" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                    <div v-if="form.errors.uom" class="text-red-500 text-xs mt-1">{{ form.errors.uom }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Classification Code (UNSPSC)</label>
                                    <input v-model="form.classification" type="text" placeholder="e.g. 10101502" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                    <div v-if="form.errors.classification" class="text-red-500 text-xs mt-1">{{ form.errors.classification }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Manufacturer Part ID</label>
                                    <input v-model="form.manufacturer_part_id" type="text" placeholder="e.g. MFR-123-ABC" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                    <div v-if="form.errors.manufacturer_part_id" class="text-red-500 text-xs mt-1">{{ form.errors.manufacturer_part_id }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Manufacturer Name</label>
                                    <input v-model="form.manufacturer_name" type="text" placeholder="e.g. Global Manufacturing Inc." class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                    <div v-if="form.errors.manufacturer_name" class="text-red-500 text-xs mt-1">{{ form.errors.manufacturer_name }}</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Categories</label>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="category in categories" :key="category.id" class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.categories" :value="category.id" class="hidden">
                                    <span :class="{'bg-[#e96a25] text-white border-[#e96a25]': form.categories.includes(category.id), 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': !form.categories.includes(category.id)}" 
                                          class="px-4 py-1.5 border rounded-full text-sm font-medium transition-colors duration-200">
                                        {{ category.name }}
                                    </span>
                                </label>
                            </div>
                            <div v-if="form.errors.categories" class="text-red-500 text-xs mt-1">{{ form.errors.categories }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Product Images</label>
                            <input @change="handleImageUpload" type="file" multiple accept="image/*" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm text-sm file:mr-4 file:py-2 file:px-4 file:rounded-l-md file:border-0 file:text-sm file:font-semibold file:bg-[#1a2b4c] file:text-white hover:file:bg-[#121f38] focus:outline-none focus:ring-[#e96a25]">
                            <p class="text-xs text-gray-500 mt-1">You can select multiple images. The first image or the one marked as primary will be the main catalog image.</p>
                            <div v-if="form.errors.images" class="text-red-500 text-xs mt-1">{{ form.errors.images }}</div>
                            
                            <!-- Image Previews -->
                            <div v-if="allImages.length > 0" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div v-for="(img, idx) in allImages" :key="idx" class="relative group border rounded-lg overflow-hidden" :class="{'ring-2 ring-[#e96a25]': img.isPrimary}">
                                    <img :src="img.url" class="w-full h-32 object-cover" />
                                    
                                    <!-- Primary Badge -->
                                    <div v-if="img.isPrimary" class="absolute top-2 left-2 bg-[#e96a25] text-white text-xs px-2 py-1 rounded shadow">
                                        Primary
                                    </div>
                                    
                                    <!-- Actions overlay -->
                                    <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                        <button v-if="!img.isPrimary" @click.prevent="setPrimary(img)" type="button" class="p-1 bg-white rounded text-gray-800 hover:text-[#e96a25] text-xs font-semibold" title="Set as Primary">
                                            Main
                                        </button>
                                        <button @click.prevent="removeImage(img)" type="button" class="p-1 bg-white rounded text-red-600 hover:bg-red-50" title="Remove">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-[#e96a25] hover:bg-[#d55e1d] text-white px-4 py-2 rounded text-sm font-bold" :disabled="form.processing">
                                {{ isEditing ? 'Update Product' : 'Create Product' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
