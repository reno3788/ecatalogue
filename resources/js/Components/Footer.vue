<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const companyName = computed(() => page.props.appSettings?.name || 'PT. Metro Pacific Tbk');
const currentYear = new Date().getFullYear(); 
</script>

<template>
    <footer class="bg-white border-t border-gray-100 py-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500">
            <p>&copy; {{ currentYear }} {{ companyName }}. All rights reserved.</p>
        </div>
    </footer>
</template>
