<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-xs font-bold uppercase tracking-widest text-gray-700 shadow-sm transition-all duration-200 ease-in-out hover:bg-gray-50 hover:text-gray-900 active:scale-95 focus:outline-none focus:ring-2 focus:ring-gray-200 focus:ring-offset-2 disabled:opacity-50"
    >
        <slot />
    </button>
</template>
