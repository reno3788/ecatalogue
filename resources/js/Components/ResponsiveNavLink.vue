<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#e96a25] text-start text-base font-bold text-gray-900 bg-[#fcece3]/40 focus:outline-none focus:text-gray-900 focus:bg-[#fcece3] focus:border-[#d55e1d] transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 hover:border-[#e96a25]/50 focus:outline-none focus:text-gray-900 focus:bg-gray-50 focus:border-[#e96a25] transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
