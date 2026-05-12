<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => []
    },
});
</script>

<template>
    <div v-if="links && links.length > 3">
        <div class="flex flex-wrap -mb-1 justify-center sm:justify-end mt-4">
            <template v-for="(link, key) in links" :key="key">
                <div v-if="link.url === null" 
                    class="mr-1 mb-1 px-4 py-2 text-sm leading-4 text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed" 
                    v-html="link.label" />
                <Link v-else 
                    class="mr-1 mb-1 px-4 py-2 text-sm leading-4 border border-gray-200 rounded-lg hover:bg-white focus:border-[#e96a25] focus:text-[#e96a25] transition duration-200" 
                    :class="{ 'bg-[#e96a25] text-white font-bold border-[#e96a25] hover:bg-[#e96a25]': link.active, 'bg-white text-gray-700': !link.active }" 
                    :href="link.url" 
                    v-html="link.label" />
            </template>
        </div>
    </div>
</template>
