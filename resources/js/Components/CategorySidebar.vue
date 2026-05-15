<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    categories: { type: Array, required: true },
    // getCategoryCount must be provided as a function prop by the parent
    getCategoryCount: { type: Function, required: true },
});

const selectedCategoryId = defineModel('selectedCategoryId', { default: null });

const rootCategories = computed(() => {
    return props.categories
        .filter(c => !c.parent_id)
        .slice()
        .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));
});

const getCategoryChildren = (pid) => {
    return props.categories
        .filter(c => Number(c.parent_id) === Number(pid))
        .slice()
        .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));
};

const expandedCategories = ref([]);
const toggleExpand = (id) => {
    if (expandedCategories.value.includes(id))
        expandedCategories.value = expandedCategories.value.filter(x => x !== id);
    else
        expandedCategories.value.push(id);
};
const isExpanded = (id) => expandedCategories.value.includes(id);

// Auto-expand ancestors when a category is selected
watch(selectedCategoryId, (newId) => {
    if (!newId) return;
    const cat = props.categories.find(c => Number(c.id) === Number(newId));
    if (cat?.parent_id) {
        if (!expandedCategories.value.includes(cat.parent_id)) expandedCategories.value.push(cat.parent_id);
        const parent = props.categories.find(c => Number(c.id) === Number(cat.parent_id));
        if (parent?.parent_id && !expandedCategories.value.includes(parent.parent_id))
            expandedCategories.value.push(parent.parent_id);
    }
}, { immediate: true });
</script>

<template>
    <div class="lg:col-span-3 bg-white border border-gray-100 rounded-2xl p-5 shadow-sm sticky top-6">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-50">
            <h3 class="font-bold text-xs text-[#1a2b4c] uppercase tracking-wider">Categories</h3>
            <span class="text-[10px] bg-[#e96a25]/10 text-[#e96a25] px-2 py-0.5 rounded-full font-bold">{{ categories.length }}</span>
        </div>

        <div class="space-y-1.5 max-h-[480px] overflow-y-auto overflow-x-hidden pr-1">
            <!-- All Categories -->
            <button
                @click="selectedCategoryId = null"
                type="button"
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                :class="selectedCategoryId === null ? 'bg-[#e96a25]/10 text-[#e96a25]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
            >
                <span class="flex items-center space-x-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>All Categories</span>
                </span>
                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black transition-colors duration-200"
                    :class="selectedCategoryId === null ? 'bg-[#e96a25]/20 text-[#e96a25]' : 'bg-gray-100 text-gray-400'">
                    {{ getCategoryCount(null) }}
                </span>
            </button>

            <!-- Root Level -->
            <div v-for="cat in rootCategories" :key="cat.id" class="space-y-1.5">
                <div class="flex items-center justify-between group">
                    <button
                        @click="selectedCategoryId = cat.id"
                        type="button"
                        class="flex-grow text-left text-xs py-2.5 px-3.5 rounded-xl font-bold transition duration-200 flex items-center justify-between"
                        :class="Number(selectedCategoryId) === Number(cat.id) ? 'bg-[#e96a25]/10 text-[#e96a25]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                    >
                        <span class="flex items-center space-x-2.5 truncate">
                            <span class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                                :class="Number(selectedCategoryId) === Number(cat.id) ? 'bg-[#e96a25] scale-110 shadow-sm' : 'bg-gray-300 group-hover:bg-[#e96a25]'"></span>
                            <span class="truncate">{{ cat.name }}</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-black flex-shrink-0 transition-colors duration-200"
                            :class="Number(selectedCategoryId) === Number(cat.id) ? 'bg-[#e96a25]/20 text-[#e96a25]' : 'bg-gray-100 text-gray-400'">
                            {{ getCategoryCount(cat.id) }}
                        </span>
                    </button>

                    <button
                        v-if="getCategoryChildren(cat.id).length > 0"
                        @click.stop="toggleExpand(cat.id)"
                        class="p-1.5 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-700 transition duration-150 mr-1 flex-shrink-0"
                    >
                        <svg class="w-3 h-3 transform transition-transform duration-200"
                            :class="{ 'rotate-90 text-[#e96a25]': isExpanded(cat.id) }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Level 2 -->
                <div v-if="getCategoryChildren(cat.id).length > 0 && isExpanded(cat.id)"
                    class="pl-4 border-l border-dashed border-gray-100 space-y-1 py-1 ml-4">
                    <div v-for="child in getCategoryChildren(cat.id)" :key="child.id" class="space-y-1">
                        <div class="flex items-center justify-between group/sub">
                            <button
                                @click="selectedCategoryId = child.id"
                                type="button"
                                class="flex-grow text-left text-[11px] py-1.5 px-2.5 rounded-lg font-semibold transition duration-200 flex items-center justify-between"
                                :class="Number(selectedCategoryId) === Number(child.id) ? 'bg-[#e96a25]/10 text-[#e96a25]' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                            >
                                <span class="flex items-center space-x-2 truncate">
                                    <span class="w-1 h-1 rounded-full transition-all duration-200"
                                        :class="Number(selectedCategoryId) === Number(child.id) ? 'bg-[#e96a25] scale-110' : 'bg-gray-300 group-hover/sub:bg-[#e96a25]'"></span>
                                    <span class="truncate">{{ child.name }}</span>
                                </span>
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black flex-shrink-0 transition-colors duration-200"
                                    :class="Number(selectedCategoryId) === Number(child.id) ? 'bg-[#e96a25]/20 text-[#e96a25]' : 'bg-gray-100 text-gray-400'">
                                    {{ getCategoryCount(child.id) }}
                                </span>
                            </button>

                            <button
                                v-if="getCategoryChildren(child.id).length > 0"
                                @click.stop="toggleExpand(child.id)"
                                class="p-1 hover:bg-gray-100 rounded text-gray-400 hover:text-gray-600 transition flex-shrink-0"
                            >
                                <svg class="w-3 h-3 transform transition-transform duration-200"
                                    :class="{ 'rotate-90 text-[#e96a25]': isExpanded(child.id) }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Level 3 -->
                        <div v-if="getCategoryChildren(child.id).length > 0 && isExpanded(child.id)"
                            class="pl-3 border-l border-dotted border-gray-100 space-y-1 ml-3">
                            <button
                                v-for="gchild in getCategoryChildren(child.id)"
                                :key="gchild.id"
                                @click="selectedCategoryId = gchild.id"
                                type="button"
                                class="w-full text-left text-[11px] py-1 px-2 rounded font-medium transition duration-200 flex items-center justify-between"
                                :class="Number(selectedCategoryId) === Number(gchild.id) ? 'bg-[#e96a25]/10 text-[#e96a25] font-semibold' : 'text-gray-400 hover:bg-gray-50 hover:text-gray-700'"
                            >
                                <span class="truncate flex items-center space-x-1.5">
                                    <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                    <span class="truncate">{{ gchild.name }}</span>
                                </span>
                                <span class="px-1 py-0.5 rounded text-[8px] font-black flex-shrink-0 transition-colors duration-200"
                                    :class="Number(selectedCategoryId) === Number(gchild.id) ? 'bg-[#e96a25]/20 text-[#e96a25]' : 'bg-gray-100 text-gray-400'">
                                    {{ getCategoryCount(gchild.id) }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
