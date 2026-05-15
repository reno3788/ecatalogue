<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const companyName = computed(() => page.props.appSettings?.name || 'PT. Metro Pacific Tbk');
const currentYear = new Date().getFullYear(); 

const isAdmin = computed(() => {
    return page.props.auth?.user && page.props.auth?.roles?.includes('admin');
});

const isSupplier = computed(() => {
    const supplierRoles = ['supplier_admin', 'supplier_processor', 'supplier_approver'];
    return page.props.auth?.user && page.props.auth?.roles?.some(r => supplierRoles.includes(r));
});

const hasSidebar = computed(() => {
    return isAdmin.value || isSupplier.value;
});
</script>

<template>
    <footer 
        class="bg-white/80 backdrop-blur-md border-t border-gray-200/60 py-4 fixed bottom-0 right-0 z-30 transition-all duration-300 shadow-sm"
        :class="[
            hasSidebar ? 'md:left-64 left-0' : 'left-0'
        ]"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs sm:text-sm text-gray-500 font-medium tracking-wide">
            <p>&copy; {{ currentYear }} {{ companyName }}. All rights reserved.</p>
        </div>
    </footer>
</template>
