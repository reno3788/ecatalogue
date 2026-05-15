<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import Footer from '@/Components/Footer.vue';
import Toast from '@/Components/Toast.vue';
import AdminSidebar from '@/Components/AdminSidebar.vue';

const showingNavigationDropdown = ref(false);

const page = usePage();

const isAdmin = computed(() => page.props.auth?.roles?.includes('admin'));
const isSupplier = computed(() => {
    const supplierRoles = ['supplier_admin', 'supplier_processor', 'supplier_approver'];
    return page.props.auth?.roles?.some(r => supplierRoles.includes(r));
});

const hasSidebar = computed(() => isAdmin.value || isSupplier.value);
const canAccessCart = computed(() => {
    // Only allow non-admin, non-supplier users (aka Buyers) to access cart
    return !isAdmin.value && !isSupplier.value;
});

const pendingApprovals = computed(() => page.props.pendingApprovals || []);
const pendingRfqs = computed(() => page.props.pendingRfqs || []);
const totalTasksCount = computed(() => pendingApprovals.value.length + pendingRfqs.value.length);

const getOrderLink = (id) => {
    try {
        const routeName = hasSidebar.value ? 'admin.orders.index' : 'dashboard';
        return route(routeName, { open_order: id });
    } catch (e) {
        return '#';
    }
};

const formatCurrency = (val) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(val); }
    catch (e) { return `${currency} ${Number(val).toFixed(2)}`; }
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-50 flex">
            <!-- Left Sidebar (For Admins and Supplier Admins) -->
            <AdminSidebar />

            <!-- Main Workspace (Right Panel) -->
            <div class="flex-grow flex flex-col min-h-screen bg-gray-50 overflow-hidden w-full justify-between">
                <div>
                    <nav class="border-b border-gray-200 bg-white">
                        <!-- Primary Navigation Menu -->
                        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div class="flex h-16 justify-between">
                                <div class="flex">
                                    <!-- Logo (Only shown if NOT admin, as admin has the sidebar logo) -->
                                    <div v-if="!hasSidebar" class="flex shrink-0 items-center">
                                        <Link :href="route('dashboard')" class="flex items-center space-x-2">
                                            <span class="font-black text-2xl tracking-tighter text-[#1a2b4c] italic">M</span>
                                            <div class="flex flex-col leading-none">
                                                <span class="font-bold text-sm tracking-widest text-[#1a2b4c]">MODERN</span>
                                                <span class="font-light text-xs tracking-widest text-gray-500">CATALOGUE</span>
                                            </div>
                                        </Link>
                                    </div>

                                    <!-- Navigation Links -->
                                    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                        <NavLink
                                            v-if="!hasSidebar"
                                            :href="route('dashboard')"
                                            :active="route().current('dashboard')"
                                        >
                                            Dashboard
                                        </NavLink>
                                        <NavLink
                                            v-if="!hasSidebar"
                                            :href="route('catalog.index')"
                                            :active="route().current('catalog.*')"
                                        >
                                            E-Catalogue
                                        </NavLink>
                                        <NavLink
                                            v-if="!hasSidebar && canAccessCart"
                                            :href="route('cart.index')"
                                            :active="route().current('cart.*')"
                                        >
                                            Cart
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="hidden sm:ms-6 sm:flex sm:items-center">
                                    <!-- Tasks / Pending Approvals Dropdown -->
                                    <div class="relative ms-3">
                                        <Dropdown align="right" width="80">
                                            <template #trigger>
                                                <span class="inline-flex rounded-md">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center justify-center rounded-full bg-white p-2 text-gray-600 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-900 focus:outline-none relative"
                                                    >
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                        </svg>
                                                        <!-- Badge Indicator -->
                                                        <span v-if="totalTasksCount > 0" class="absolute top-1.5 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white border border-white ring-2 ring-white">
                                                            {{ totalTasksCount }}
                                                        </span>
                                                    </button>
                                                </span>
                                            </template>

                                            <template #content>
                                                <div class="px-4 py-2 font-semibold text-gray-700 border-b border-gray-100 text-sm flex items-center justify-between">
                                                    <span>Tasks To Do</span>
                                                    <span v-if="totalTasksCount > 0" class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs">{{ totalTasksCount }} Pending</span>
                                                </div>
                                                
                                                <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                                                    <div v-if="totalTasksCount === 0" class="px-4 py-6 text-center text-gray-500 text-sm">
                                                        <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                        </svg>
                                                        You are all caught up!
                                                    </div>
                                                    
                                                    <!-- 1. Pending Approvals -->
                                                    <a 
                                                        v-for="item in pendingApprovals" 
                                                        :key="'appr-' + item.id"
                                                        :href="getOrderLink(item.id)"
                                                        class="block px-4 py-3 hover:bg-amber-50/20 text-sm transition duration-150"
                                                    >
                                                        <div class="flex justify-between items-start mb-1">
                                                            <span class="font-bold text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wider border border-amber-100">Approval Required</span>
                                                            <span class="text-[10px] text-gray-400">{{ item.created_at }}</span>
                                                        </div>
                                                        <p class="text-gray-600 text-xs mt-1">Order #{{ item.id }} for <strong class="text-gray-800">{{ item.company_name }}</strong> requires your signature.</p>
                                                        <p class="mt-1 text-xs text-blue-600 font-bold">{{ formatCurrency(item.total) }}</p>
                                                    </a>

                                                    <!-- 2. Pending RFQs to be Submitted -->
                                                    <a 
                                                        v-for="item in pendingRfqs" 
                                                        :key="'rfq-' + item.id"
                                                        :href="getOrderLink(item.id)"
                                                        class="block px-4 py-3 hover:bg-indigo-50/20 text-sm transition duration-150"
                                                    >
                                                        <div class="flex justify-between items-start mb-1">
                                                            <span class="font-bold text-indigo-800 bg-indigo-50 px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wider border border-indigo-100">RFQ to be Submitted</span>
                                                            <span class="text-[10px] text-gray-400">{{ item.created_at }}</span>
                                                        </div>
                                                        <p class="text-gray-600 text-xs mt-1">Order #{{ item.id }} for <strong class="text-gray-800">{{ item.company_name }}</strong> is sitting as RFQ.</p>
                                                        <p class="mt-1 text-xs text-[#e96a25] font-bold">{{ formatCurrency(item.total) }}</p>
                                                    </a>
                                                </div>
                                            </template>
                                        </Dropdown>
                                    </div>

                                    <!-- Settings Dropdown -->
                                    <div class="relative ms-3">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <span class="inline-flex rounded-md">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-900 transition duration-150 ease-in-out hover:text-gray-900 focus:outline-none"
                                                    >
                                                        <svg class="w-5 h-5 mr-1.5 -ml-0.5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        {{ $page.props.auth.user.name }}

                                                        <svg
                                                            class="-me-0.5 ms-2 h-4 w-4"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 20 20"
                                                            fill="currentColor"
                                                        >
                                                            <path
                                                                fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                                clip-rule="evenodd"
                                                            />
                                                        </svg>
                                                    </button>
                                                </span>
                                            </template>

                                            <template #content>
                                                <DropdownLink
                                                    :href="route('profile.edit')"
                                                >
                                                    Profile
                                                </DropdownLink>
                                                <DropdownLink
                                                    :href="route('logout')"
                                                    method="post"
                                                    as="button"
                                                >
                                                    Log Out
                                                </DropdownLink>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </div>

                                <!-- Hamburger -->
                                <div class="-me-2 flex items-center sm:hidden">
                                    <button
                                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            stroke="currentColor"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                :class="{
                                                    hidden: showingNavigationDropdown,
                                                    'inline-flex': !showingNavigationDropdown,
                                                }"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 6h16M4 12h16M4 18h16"
                                            />
                                            <path
                                                :class="{
                                                    hidden: !showingNavigationDropdown,
                                                    'inline-flex': showingNavigationDropdown,
                                                }"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Responsive Navigation Menu -->
                        <div
                            :class="{
                                block: showingNavigationDropdown,
                                hidden: !showingNavigationDropdown,
                            }"
                            class="sm:hidden"
                        >
                            <div class="space-y-1 pb-3 pt-2">
                                <ResponsiveNavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    :href="route('catalog.index')"
                                    :active="route().current('catalog.*')"
                                >
                                    E-Catalogue
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="canAccessCart"
                                    :href="route('cart.index')"
                                    :active="route().current('cart.*')"
                                >
                                    Cart
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    v-if="hasSidebar"
                                    :href="isAdmin ? route('admin.dashboard') : route('admin.orders.index')"
                                    :active="route().current('admin.*')"
                                >
                                    Admin Panel
                                </ResponsiveNavLink>
                            </div>

                            <!-- Responsive Settings Options -->
                            <div class="border-t border-gray-200 pb-1 pt-4">
                                <div class="px-4">
                                    <div class="text-base font-medium text-gray-800">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div class="text-sm font-medium text-gray-500">
                                        {{ $page.props.auth.user.email }}
                                    </div>
                                </div>

                                <div class="mt-3 space-y-1">
                                    <ResponsiveNavLink :href="route('profile.edit')">
                                        Profile
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                    >
                                        Log Out
                                    </ResponsiveNavLink>
                                </div>
                            </div>
                        </div>
                    </nav>

                    <!-- Page Heading -->
                    <header
                        class="bg-white shadow-sm border-b border-gray-200"
                        v-if="$slots.header"
                    >
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            <slot name="header" />
                        </div>
                    </header>

                    <!-- Page Content -->
                    <main class="pt-8 pb-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
                        <slot />
                    </main>
                </div>
                <Footer />
            </div>
        </div>
        <Toast />
    </div>
</template>
