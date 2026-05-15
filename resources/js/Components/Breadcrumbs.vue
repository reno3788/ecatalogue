<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true, // array of { label: String, href?: String, icon?: String }
    },
});
</script>

<template>
    <nav class="flex mb-6 overflow-x-auto no-scrollbar py-2 bg-gray-50/70 border border-gray-100 rounded-xl px-4 max-w-fit shadow-sm" aria-label="Breadcrumb">
        <ol class="inline-flex items-center gap-1 md:gap-2 flex-nowrap text-xs md:text-sm">
            <li v-for="(item, index) in items" :key="index" class="inline-flex items-center whitespace-nowrap">
                <div class="flex items-center gap-1.5 md:gap-2">
                    <!-- Separator Chevron -->
                    <svg v-if="index > 0" class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                    
                    <!-- Link Node -->
                    <Link v-if="item.href" :href="item.href" class="inline-flex items-center font-bold text-gray-500 hover:text-[#e96a25] transition-all duration-200">
                        <!-- Elegant Icon Hook (e.g. for dashboard or root) -->
                        <svg v-if="item.icon === 'dashboard'" class="w-4 h-4 mr-1.5 text-gray-400 group-hover:text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <svg v-if="item.icon === 'catalog'" class="w-4 h-4 mr-1.5 text-gray-400 group-hover:text-[#e96a25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                    
                    <!-- Active Leaf Node -->
                    <span v-else class="inline-flex items-center font-black text-[#1a2b4c]">
                        <svg v-if="item.icon === 'edit'" class="w-3.5 h-3.5 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </span>
                </div>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
