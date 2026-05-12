<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    categories: Array,
});

// UI View Toggle: 'tree' or 'table'
const activeView = ref('tree');

// Reactive Category List
const localCategories = ref([...props.categories]);

// Drag and Drop States
const draggedCategory = ref(null);
const hoveredCategoryId = ref(null);
const isHoveringRootZone = ref(false);

// Messages
const successMessage = ref(null);
const errorMessage = ref(null);

// Temporary loading state
const isProcessing = ref(false);

// Show Toast Helpers
const showToast = (message, type = 'success') => {
    if (type === 'success') {
        successMessage.value = message;
        errorMessage.value = null;
        setTimeout(() => { successMessage.value = null; }, 4000);
    } else {
        errorMessage.value = message;
        successMessage.value = null;
        setTimeout(() => { errorMessage.value = null; }, 5000);
    }
};

// Tree Structure Builders
const rootCategories = computed(() => {
    return localCategories.value.filter(c => !c.parent_id);
});

const getChildren = (parentId) => {
    return localCategories.value.filter(c => c.parent_id === parentId);
};

// HTML5 Drag Handlers
const onDragStart = (event, category) => {
    draggedCategory.value = category;
    event.dataTransfer.effectAllowed = 'move';
    // Smooth custom drag preview or styling
    event.target.classList.add('opacity-40');
};

const onDragEnd = (event) => {
    event.target.classList.remove('opacity-40');
    draggedCategory.value = null;
    hoveredCategoryId.value = null;
    isHoveringRootZone.value = false;
};

// Local helpers to calculate category depth & subtree height
const getSubtreeHeight = (catId) => {
    const children = localCategories.value.filter(c => c.parent_id === catId);
    if (children.length === 0) return 1;
    let maxHeight = 0;
    children.forEach(child => {
        maxHeight = Math.max(maxHeight, getSubtreeHeight(child.id));
    });
    return 1 + maxHeight;
};

const getNodeDepth = (catId) => {
    let depth = 1;
    let current = localCategories.value.find(c => c.id === catId);
    while (current && current.parent_id) {
        depth++;
        current = localCategories.value.find(c => c.id === current.parent_id);
    }
    return depth;
};

const onDragOver = (event, targetCategory) => {
    if (!draggedCategory.value || draggedCategory.value.id === targetCategory.id) return;
    
    // Check circular references before showing hover state
    let tempParent = targetCategory;
    let isDescendant = false;
    while (tempParent) {
        if (tempParent.id === draggedCategory.value.id) {
            isDescendant = true;
            break;
        }
        tempParent = localCategories.value.find(c => c.id === tempParent.parent_id);
    }
    
    // Check 3-level depth limit
    const parentDepth = getNodeDepth(targetCategory.id);
    const subtreeHeight = getSubtreeHeight(draggedCategory.value.id);
    if (parentDepth + subtreeHeight > 3) {
        return; // Exceeds max 3 levels limit
    }
    
    if (!isDescendant) {
        event.preventDefault();
        hoveredCategoryId.value = targetCategory.id;
    }
};

const onDragLeave = (category) => {
    if (hoveredCategoryId.value === category.id) {
        hoveredCategoryId.value = null;
    }
};

const onDrop = async (event, targetCategory) => {
    event.preventDefault();
    const dragged = draggedCategory.value;
    if (!dragged || dragged.id === targetCategory.id) return;

    // Depth check
    const parentDepth = getNodeDepth(targetCategory.id);
    const subtreeHeight = getSubtreeHeight(dragged.id);
    if (parentDepth + subtreeHeight > 3) {
        showToast('Hierarchy depth cannot exceed 3 levels.', 'error');
        return;
    }

    // Call update parenting API
    await updateCategoryParent(dragged.id, targetCategory.id);
};

const onDragOverRoot = (event) => {
    if (draggedCategory.value && draggedCategory.value.parent_id) {
        event.preventDefault();
        isHoveringRootZone.value = true;
    }
};

const onDragLeaveRoot = () => {
    isHoveringRootZone.value = false;
};

const onDropRoot = async (event) => {
    event.preventDefault();
    const dragged = draggedCategory.value;
    if (!dragged || !dragged.parent_id) return;

    await updateCategoryParent(dragged.id, null);
};

// Axios Request to Update Parenting
const updateCategoryParent = async (categoryId, parentId) => {
    isProcessing.value = true;
    try {
        const response = await axios.put(route('admin.categories.update-parent', categoryId), {
            parent_id: parentId
        });

        if (response.data.success) {
            localCategories.value = response.data.categories;
            showToast(response.data.message || 'Category hierarchy updated successfully!');
        }
    } catch (err) {
        const errMsg = err.response?.data?.error || 'Failed to update category relationship.';
        showToast(errMsg, 'error');
    } finally {
        isProcessing.value = false;
        draggedCategory.value = null;
        hoveredCategoryId.value = null;
        isHoveringRootZone.value = false;
    }
};

// Force Delete handling via inertia
const handleDelete = (categoryId) => {
    if (confirm('Are you sure you want to delete this category? This will also delete all of its subcategories.')) {
        router.delete(route('admin.categories.destroy', categoryId), {
            onSuccess: () => {
                // Refresh local categories state
                localCategories.value = localCategories.value.filter(c => c.id !== categoryId);
                showToast('Category deleted successfully.');
            }
        });
    }
};
</script>

<template>
    <Head title="Manage Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Manage Categories</h2>
                    <p class="text-sm text-gray-500 mt-1">Structure and nest your product categories with intuitive drag-and-drop.</p>
                </div>
                <Link :href="route('admin.categories.create')" class="bg-[#e96a25] hover:bg-[#d55e1d] text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-md hover:shadow-lg transition-all duration-150 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Category
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Toast Notifications -->
                <Transition name="fade">
                    <div v-if="successMessage" class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-emerald-500 bg-emerald-100 rounded-full p-1.5 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium text-emerald-800">{{ successMessage }}</span>
                        </div>
                        <button @click="successMessage = null" class="text-emerald-400 hover:text-emerald-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </Transition>

                <Transition name="fade">
                    <div v-if="errorMessage" class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-rose-500 bg-rose-100 rounded-full p-1.5 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium text-rose-800">{{ errorMessage }}</span>
                        </div>
                        <button @click="errorMessage = null" class="text-rose-400 hover:text-rose-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </Transition>

                <!-- Toolbar and Toggles -->
                <div class="flex items-center justify-between bg-white px-4 py-3 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center gap-2 bg-gray-100 p-1 rounded-lg">
                        <button 
                            @click="activeView = 'tree'" 
                            :class="[activeView === 'tree' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800', 'px-4 py-2 rounded-md text-sm font-bold transition-all duration-150 flex items-center gap-2']"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Hierarchical Tree
                        </button>
                        <button 
                            @click="activeView = 'table'" 
                            :class="[activeView === 'table' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800', 'px-4 py-2 rounded-md text-sm font-bold transition-all duration-150 flex items-center gap-2']"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Flat Table List
                        </button>
                    </div>

                    <div v-if="isProcessing" class="flex items-center gap-2 text-sm text-gray-500 font-medium">
                        <svg class="animate-spin h-4 w-4 text-[#e96a25]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving positions...
                    </div>
                </div>

                <!-- TREE DRAG AND DROP BUILDER -->
                <div v-if="activeView === 'tree'" class="space-y-6">
                    <!-- Root Droppable Target -->
                    <div 
                        @dragover="onDragOverRoot" 
                        @dragleave="onDragLeaveRoot" 
                        @drop="onDropRoot"
                        :class="[
                            isHoveringRootZone 
                                ? 'border-[#e96a25] bg-orange-50/80 scale-[1.01] shadow-md text-[#e96a25]' 
                                : 'border-gray-300 bg-white hover:border-[#e96a25] text-gray-500 hover:text-gray-700',
                            'border-2 border-dashed rounded-xl p-5 text-center transition-all duration-200 cursor-default flex flex-col items-center justify-center gap-2 font-bold text-sm'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span>📂 Drag and Drop subcategories here to make them Root (Top-Level)</span>
                    </div>

                    <!-- Main Nested Category Tree -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                        <div v-if="rootCategories.length === 0" class="text-center py-12 text-gray-500">
                            No categories found. Click "Add Category" to get started.
                        </div>

                        <!-- Nested Loop Level 1 (Root) -->
                        <div v-for="rootCat in rootCategories" :key="rootCat.id" class="border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow transition-shadow">
                            <div 
                                draggable="true"
                                @dragstart="onDragStart($event, rootCat)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver($event, rootCat)"
                                @dragleave="onDragLeave(rootCat)"
                                @drop="onDrop($event, rootCat)"
                                :class="[
                                    hoveredCategoryId === rootCat.id ? 'bg-orange-50/80 border-l-4 border-l-[#e96a25]' : 'bg-gray-50/80',
                                    'p-4 flex items-center justify-between border-b border-gray-100 cursor-grab active:cursor-grabbing transition-colors duration-150'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                        </svg>
                                    </span>
                                    <span class="font-bold text-gray-800">{{ rootCat.name }}</span>
                                    <span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-[#e96a25] text-xs font-semibold">
                                        {{ rootCat.products_count ?? 0 }} Products
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Link :href="route('admin.categories.edit', rootCat.id)" class="text-xs font-bold text-gray-600 hover:text-[#e96a25] transition-colors bg-white px-2.5 py-1.5 rounded-md border border-gray-200">
                                        Edit
                                    </Link>
                                    <button @click="handleDelete(rootCat.id)" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors bg-white px-2.5 py-1.5 rounded-md border border-gray-200">
                                        Delete
                                    </button>
                                </div>
                            </div>

                            <!-- Level 2 Subcategories -->
                            <div class="p-4 bg-white space-y-3 pl-8 md:pl-12 border-l-2 border-dashed border-gray-100">
                                <div v-for="subCat in getChildren(rootCat.id)" :key="subCat.id" class="border border-gray-100 rounded-lg overflow-hidden shadow-sm">
                                    <div 
                                        draggable="true"
                                        @dragstart="onDragStart($event, subCat)"
                                        @dragend="onDragEnd"
                                        @dragover="onDragOver($event, subCat)"
                                        @dragleave="onDragLeave(subCat)"
                                        @drop="onDrop($event, subCat)"
                                        :class="[
                                            hoveredCategoryId === subCat.id ? 'bg-orange-50 border-l-4 border-l-[#e96a25]' : 'bg-gray-50/40',
                                            'p-3.5 flex items-center justify-between cursor-grab active:cursor-grabbing transition-colors'
                                        ]"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span class="text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                </svg>
                                            </span>
                                            <span class="font-semibold text-gray-700">{{ subCat.name }}</span>
                                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold">
                                                {{ subCat.products_count ?? 0 }} Products
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Link :href="route('admin.categories.edit', subCat.id)" class="text-xs font-bold text-gray-600 hover:text-[#e96a25] transition-colors bg-white px-2 py-1 rounded border border-gray-200">
                                                Edit
                                            </Link>
                                            <button @click="handleDelete(subCat.id)" class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors bg-white px-2 py-1 rounded border border-gray-200">
                                                Delete
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Level 3 Subcategories -->
                                    <div v-if="getChildren(subCat.id).length > 0" class="p-3 bg-white space-y-2 pl-8 md:pl-12 border-l-2 border-dashed border-gray-100">
                                        <div 
                                            v-for="deepCat in getChildren(subCat.id)" 
                                            :key="deepCat.id"
                                            draggable="true"
                                            @dragstart="onDragStart($event, deepCat)"
                                            @dragend="onDragEnd"
                                            @dragover="onDragOver($event, deepCat)"
                                            @dragleave="onDragLeave(deepCat)"
                                            @drop="onDrop($event, deepCat)"
                                            :class="[
                                                hoveredCategoryId === deepCat.id ? 'bg-orange-50 border-l-4 border-l-[#e96a25]' : 'bg-gray-50/20',
                                                'p-3 flex items-center justify-between border border-gray-100 rounded-md cursor-grab active:cursor-grabbing transition-colors'
                                            ]"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </span>
                                                <span class="text-sm font-medium text-gray-600">{{ deepCat.name }}</span>
                                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-gray-50 text-gray-500 font-bold border border-gray-100">
                                                    {{ deepCat.products_count ?? 0 }} Products
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <Link :href="route('admin.categories.edit', deepCat.id)" class="text-[11px] font-bold text-gray-600 hover:text-[#e96a25] transition-colors bg-white px-1.5 py-1 rounded border border-gray-100">
                                                    Edit
                                                </Link>
                                                <button @click="handleDelete(deepCat.id)" class="text-[11px] font-bold text-red-600 hover:text-red-800 transition-colors bg-white px-1.5 py-1 rounded border border-gray-100">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="getChildren(rootCat.id).length === 0" class="text-xs text-gray-400 italic">
                                    No subcategories. Drag other categories here to nest them.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE LIST VIEW -->
                <div v-if="activeView === 'table'" class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Slug</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Parent Category</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Products Count</th>
                                <th class="px-6 py-4 class-right text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="category in localCategories" :key="category.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ category.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ category.slug }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span v-if="category.parent_id" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        📁 {{ localCategories.find(c => c.id === category.parent_id)?.name || 'Parent Category' }}
                                    </span>
                                    <span v-else class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">
                                        Root Category
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 py-0.5 rounded-full bg-orange-50 text-[#e96a25] text-xs font-bold">
                                        {{ category.products_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">   
                                        <Link :href="route('admin.categories.edit', category.id)" class="p-1.5 text-gray-400 hover:text-[#1a2b4c] transition"
                                                            title="Edit Category"> 
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                        </Link>
                                        <button @click="handleDelete(category.id)" class="p-1.5 text-gray-400 hover:text-red-500 transition"
                                                            title="Delete Category">  
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="localCategories.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No categories found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
