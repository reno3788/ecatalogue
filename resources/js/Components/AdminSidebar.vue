<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

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
    <!-- Left Sidebar (For Admins and Supplier Admins) -->
    <aside v-if="hasSidebar" class="w-64 bg-white border-r border-gray-200 flex-shrink-0 min-h-screen hidden md:block">
        <div class="py-6 px-4 space-y-6">
            <div class="flex items-center space-x-2 px-4 mb-4">
                <template v-if="$page.props.appSettings?.logo_url">
                    <img :src="$page.props.appSettings.logo_url" alt="Logo" class="max-h-8 object-contain" />
                    <span class="font-bold text-sm tracking-tight text-[#1a2b4c] uppercase ml-1">{{ $page.props.appSettings?.name }}</span>
                </template>
                <template v-else>
                    <span class="font-black text-2xl tracking-tighter text-[#1a2b4c] italic">M</span>
                    <div class="flex flex-col leading-none">
                        <span class="font-bold text-sm tracking-widest text-[#1a2b4c]">MODERN</span>
                        <span class="font-light text-xs tracking-widest text-gray-500">ADMIN</span>
                    </div>
                </template>
            </div>
            <div class="space-y-1">
                <Link 
                    :href="route('admin.dashboard')" 
                    :class="[
                        'flex items-center space-x-3 px-4 py-3 rounded-lg text-sm font-semibold transition-colors block w-full',
                        route().current('admin.dashboard') ? 'bg-[#e96a25]/10 text-[#e96a25]' : 'text-gray-700 hover:bg-gray-50 hover:text-[#1a2b4c]'
                    ]"
                >
                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span>Dashboard</span>
                </Link>

                <Link 
                    :href="route('catalog.index')" 
                    :class="[
                        'flex items-center space-x-3 px-4 py-3 rounded-lg text-sm font-semibold transition-colors block w-full',
                        route().current('catalog.*') ? 'bg-[#e96a25]/10 text-[#e96a25]' : 'text-gray-700 hover:bg-gray-50 hover:text-[#1a2b4c]'
                    ]"
                >
                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>E-Catalogue</span>
                </Link>

                <div class="pt-4 border-t border-gray-100 mt-4">
                    <div class="px-4 py-1 text-xs font-bold uppercase tracking-wider text-gray-400">
                        Admin Controls
                    </div>
                    <div class="mt-2 space-y-1">
                        <Link 
                            :href="route('admin.orders.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.orders.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Orders</span>
                        </Link>
                        <Link 
                            :href="route('admin.products.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.products.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Products</span>
                        </Link>
                        <Link 
                            :href="route('admin.categories.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.categories.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Categories</span>
                        </Link>
                        <Link 
                            :href="route('admin.companies.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.companies.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Companies</span>
                        </Link>
                        <Link 
                            :href="route('admin.client-price-lists.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.client-price-lists.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Client Price List</span>
                        </Link>
                    </div>
                </div>

                <div v-if="isAdmin" class="pt-4 border-t border-gray-100 mt-4">
                    <div class="px-4 py-1 text-xs font-bold uppercase tracking-wider text-gray-400">
                        Configuration
                    </div>
                    <div class="mt-2 space-y-1">
                        <Link 
                            :href="route('admin.users.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.users.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Users Config</span>
                        </Link>
                        <Link 
                            :href="route('admin.workflows.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.workflows.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Workflow Approval</span>
                        </Link>
                        <Link 
                            :href="route('admin.app-settings.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.app-settings.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>App Settings</span>
                        </Link>
                        <Link 
                            :href="route('admin.audit-logs.index')" 
                            :class="[
                                'flex items-center space-x-3 px-4 py-2 rounded-md text-sm font-medium transition-colors w-full text-left block',
                                route().current('admin.audit-logs.*') ? 'text-[#e96a25] font-bold bg-[#e96a25]/5' : 'text-gray-600 hover:text-[#1a2b4c] hover:bg-gray-50'
                            ]"
                        >
                            <span>Audit Trail</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
