<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const page = usePage();

const isAdmin = computed(() => page.props.auth?.roles && page.props.auth.roles.includes('admin'));

const isSupplier = computed(() => {
    const supplierRoles = ['supplier_approver', 'supplier_processor', 'supplier_admin'];
    return page.props.auth?.roles?.some(r => supplierRoles.includes(r));
});

const hasSidebar = computed(() => isAdmin.value || isSupplier.value);

const canAccessCart = computed(() => {
    return !hasSidebar.value;
});

const props = defineProps({
    searchQuery: {
        type: String,
        default: '',
    },
    currentCategory: {
        type: String,
        default: null,
    }
});

const searchInput = ref(props.searchQuery);

const cartItemsCount = computed(() => {
    return page.props.cartItemsCount || 0;
});

const handleSearch = () => {
    router.get(route('catalog.index'), { q: searchInput.value, category: props.currentCategory }, { preserveState: true, preserveScroll: true });
};

const showBackToTop = ref(false);

const handleScroll = () => {
    showBackToTop.value = window.scrollY > 400;
};

const scrollToTop = () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

import Footer from '@/Components/Footer.vue';
import Toast from '@/Components/Toast.vue';
import AdminSidebar from '@/Components/AdminSidebar.vue';

</script>

<template>
    <div class="min-h-screen bg-white text-gray-800 flex">
        <!-- Left Sidebar (For Admins and Supplier Admins) -->
        <AdminSidebar />

        <!-- Main Workspace (Right Panel) -->
        <div class="main-workspace flex-grow flex flex-col min-h-screen w-full">
            <!-- Top Bar -->
        <header class="sticky top-0 z-50 bg-white/70 backdrop-blur-md border-b border-gray-200 py-4 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <!-- Logo Area -->
                    <div class="flex items-center space-x-2 shrink-0">
                        <Link :href="route('catalog.index')" class="flex items-center space-x-2">
                            <template v-if="$page.props.appSettings?.logo_url">
                                <img :src="$page.props.appSettings.logo_url" alt="Logo" class="h-10 sm:h-12 lg:h-14 w-auto object-contain" />
                            </template>
                            <template v-else>
                                <span class="font-black text-xl tracking-tight text-[#1a2b4c] uppercase">{{ $page.props.appSettings?.name || 'MODERN' }}</span>
                            </template>
                        </Link>
                    </div>

                    <!-- Search Bar (Desktop) -->
                    <div class="hidden md:block flex-1 max-w-2xl mx-4 lg:mx-8">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input 
                                v-model="searchInput" 
                                @keyup.enter="handleSearch"
                                type="text" 
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-[#e96a25] focus:ring-1 focus:ring-[#e96a25] sm:text-sm" 
                                placeholder="Search for products"
                            >
                        </div>
                    </div>

                    <!-- Right Header Items -->
                    <div class="flex items-center space-x-3 lg:space-x-6 shrink-0">
                        <!-- Profile Dropdown -->
                        <div v-if="$page.props.auth.user" class="relative flex items-center">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button type="button" class="text-[#1a2b4c] hover:text-gray-600 focus:outline-none transition-colors cursor-pointer">
                                        <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="px-4 py-2 border-b border-gray-100">
                                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</p>
                                        <p class="text-sm font-medium text-gray-800 truncate">{{ $page.props.auth.user.name }}</p>
                                    </div>
                                    <DropdownLink :href="route('profile.edit')">
                                        Profile
                                    </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                        <Link v-else :href="route('login')" class="text-[#1a2b4c] hover:text-gray-600 cursor-pointer">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </Link>
                        
                        <Link v-if="canAccessCart" :href="route('cart.index')" class="text-gray-600 hover:text-gray-900 relative">
                            <svg class="w-6 h-6 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <!-- Cart Badge -->
                            <span class="absolute -top-2 -right-2 bg-[#e96a25] text-white text-[10px] sm:text-xs font-bold rounded-full h-4 w-4 sm:h-5 sm:w-5 flex items-center justify-center">{{ cartItemsCount }}</span>
                        </Link>
                        
                        <div class="flex items-center space-x-2 sm:space-x-3">
                            <!-- Dashboard Link for Buyers (Mobile view - Icon Only) -->
                            <Link v-if="$page.props.auth.user && !hasSidebar" :href="route('dashboard')" class="p-2 bg-[#e96a25]/5 border border-[#e96a25]/15 text-[#e96a25] rounded-full hover:bg-[#e96a25] hover:text-white hover:border-[#e96a25] transition-all duration-200 flex items-center justify-center sm:hidden shadow-sm active:scale-95" title="Buyer Dashboard">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </Link>
                            <!-- Dashboard Link for Buyers (Desktop/Tablet view - Capsule) -->
                            <Link v-if="$page.props.auth.user && !hasSidebar" :href="route('dashboard')" class="hidden sm:flex items-center bg-[#e96a25]/5 border border-[#e96a25]/15 text-[#e96a25] hover:bg-[#e96a25] hover:text-white hover:border-[#e96a25] rounded-full px-3.5 py-1.5 transition-all duration-200 text-xs font-black shadow-sm hover:shadow-md active:scale-[0.98]">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <span>Dashboard</span>
                            </Link>

                            <!-- Admin/Supplier Panel Link (Mobile view - Icon Only) -->
                            <Link v-else-if="$page.props.auth.user && hasSidebar" :href="route('admin.dashboard')" class="p-2 bg-[#1a2b4c]/5 border border-[#1a2b4c]/15 text-[#1a2b4c] rounded-full hover:bg-[#1a2b4c] hover:text-white hover:border-[#1a2b4c] transition-all duration-200 flex items-center justify-center sm:hidden shadow-sm active:scale-95" title="Admin Panel">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </Link>
                            <!-- Admin/Supplier Panel Link (Desktop/Tablet view - Capsule) -->
                            <Link v-else-if="$page.props.auth.user && hasSidebar" :href="route('admin.dashboard')" class="hidden sm:flex items-center bg-[#1a2b4c]/5 border border-[#1a2b4c]/15 text-[#1a2b4c] hover:bg-[#1a2b4c] hover:text-white hover:border-[#1a2b4c] rounded-full px-3.5 py-1.5 transition-all duration-200 text-xs font-black shadow-sm hover:shadow-md active:scale-[0.98]">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Admin Panel</span>
                            </Link>

                            <!-- Guest Login Link (Mobile view - Icon Only) -->
                            <Link v-else-if="!$page.props.auth.user" :href="route('login')" class="p-2 bg-gray-50 border border-gray-200 text-gray-700 rounded-full hover:bg-gray-800 hover:text-white hover:border-gray-800 transition-all duration-200 flex items-center justify-center sm:hidden shadow-sm active:scale-95" title="Login">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                            </Link>
                            <!-- Guest Login Link (Desktop/Tablet view - Capsule) -->
                            <Link v-else-if="!$page.props.auth.user" :href="route('login')" class="hidden sm:flex items-center bg-gray-50 border border-gray-200 text-gray-700 hover:bg-gray-800 hover:text-white hover:border-gray-800 rounded-full px-3.5 py-1.5 transition-all duration-200 text-xs font-black shadow-sm hover:shadow-md active:scale-[0.98]">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                <span>Login</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Search Bar (Mobile) -->
                <div class="mt-4 block md:hidden">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            v-model="searchInput" 
                            @keyup.enter="handleSearch"
                            type="text" 
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-[#e96a25] focus:ring-1 focus:ring-[#e96a25] text-sm" 
                            placeholder="Search for products"
                        >
                    </div>
                </div>
            </div>
        </header>

        <!-- Sub Header - Removed as it's moved to Index.vue -->

        <!-- Main Content Layout -->
        <div class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-24 flex flex-col md:flex-row gap-8 w-full">
            <slot />
        </div>

        <!-- Minimalistic Footer -->
        <Footer />
        </div>
        
        <!-- Back to Top Button -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-10 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-10 opacity-0"
        >
            <button 
                v-show="showBackToTop" 
                @click="scrollToTop"
                class="fixed bottom-20 right-6 z-50 bg-[#d85a15] text-white p-3 rounded-full shadow-lg shadow-[#1a2b4c]/30 hover:bg-[#e96a25] hover:shadow-[#e96a25]/30 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-[#e96a25] focus:ring-offset-2 hover:-translate-y-1"
                aria-label="Back to top"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                </svg>
            </button>
        </Transition>

        <Toast />
    </div>
</template>


