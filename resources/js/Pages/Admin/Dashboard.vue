<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const page = usePage();

const props = defineProps({
    stats: Object,
    orders: Array,
    customers: Array,
    chartData: Array,
    filters: Object,
});

// Reactive state mapped to filters
const filterPeriod = ref(props.filters?.period || '');
const selectedGroupBy = ref(props.filters?.group_by || 'company');
const selectedMetric = ref('total'); // toggle between 'total' (value) and 'count' (volume)

// --- Premium Custom Month Picker Logic ---
const isPickerOpen = ref(false);
const pickerYear = ref(new Date().getFullYear());
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const openPicker = () => {
    if (filterPeriod.value) {
        pickerYear.value = parseInt(filterPeriod.value.split('-')[0]);
    } else {
        pickerYear.value = new Date().getFullYear();
    }
    isPickerOpen.value = !isPickerOpen.value;
};

const selectMonth = (monthNum) => {
    const formattedMonth = String(monthNum).padStart(2, '0');
    filterPeriod.value = `${pickerYear.value}-${formattedMonth}`;
    isPickerOpen.value = false;
};

const displayPeriod = computed(() => {
    if (!filterPeriod.value) return 'All Time';
    const parts = filterPeriod.value.split('-');
    if (parts.length < 2) return 'All Time';
    const y = parts[0];
    const m = parseInt(parts[1]);
    if (isNaN(m) || m < 1 || m > 12) return 'All Time';
    return `${monthNames[m - 1]} ${y}`;
});
// ---------------------------------------

// Automatic updating trigger using Inertia partial reloading
watch([filterPeriod, selectedGroupBy], () => {
    router.get(route('admin.dashboard'), {
        period: filterPeriod.value,
        group_by: selectedGroupBy.value
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['chartData', 'filters', 'stats', 'orders']
    });
});

// Dynamic selection translation
const currentMaxKey = computed(() => selectedMetric.value === 'total' ? 'overallTotal' : 'overallCount');

// Find the maximum chart value based on selected metric for dynamic vertical scaling
const maxVal = computed(() => {
    if (!props.chartData || props.chartData.length === 0) return 1;
    const max = Math.max(...props.chartData.map(d => Number(d[currentMaxKey.value] || 0)));
    return max > 0 ? max : 1;
});

// Aggregated unique collection of secondary dimension strings for explicit chart legends
const uniqueSegments = computed(() => {
    if (!props.chartData || props.chartData.length === 0) return [];
    const seen = new Set();
    props.chartData.forEach(bar => {
        (bar.segments || []).forEach(seg => seen.add(seg.label));
    });
    return Array.from(seen).sort((a, b) => a.localeCompare(b));
});

// High-Contrast Vibrancy Palette (Maximally distinct adjacent shades)
const dimensionPalette = [
  'from-[#f97316] to-[#fb923c]', // 1. Bright Orange
  'from-[#1e3a8a] to-[#3b82f6]', // 2. Deep Midnight Blue
  'from-[#10b981] to-[#34d399]', // 3. Vivid Emerald Green
  'from-[#ec4899] to-[#f472b6]', // 4. Strong Pink
  'from-[#06b6d4] to-[#22d3ee]', // 5. Cyan Sky
  'from-[#eab308] to-[#facc15]', // 6. Golden Yellow
  'from-[#8b5cf6] to-[#a78bfa]', // 7. Intense Violet
  'from-[#ef4444] to-[#f87171]', // 8. Radical Red
  'from-[#4f46e5] to-[#818cf8]', // 9. Electric Indigo
  'from-[#14b8a6] to-[#2dd4bf]', // 10. Deep Teal
  'from-[#be123c] to-[#fb7185]', // 11. Rosewood
  'from-[#15803d] to-[#4ade80]'  // 12. Forest Green
];

const getColorForSegment = (label) => {
    if (!label) return dimensionPalette[0];
    let hash = 0;
    for (let i = 0; i < label.length; i++) {
        // DJB2 Hash modification for cleaner index spreading
        hash = ((hash << 5) + hash) + label.charCodeAt(i); 
    }
    return dimensionPalette[Math.abs(hash) % dimensionPalette.length];
};

// Format currency helper
const formatCurrency = (value) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(value);
    } catch (e) {
        return `${currency} ${Number(value).toFixed(2)}`;
    }
};

// Format compact currency helper for dashboards
const formatCompactCurrency = (value) => {
    const currency = page.props.appSettings?.currency || 'EUR';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            notation: 'compact',
            compactDisplay: 'short'
        }).format(value);
    } catch (e) {
        return formatCurrency(value);
    }
};

// Format compact number helper
const formatCompactNumber = (value) => {
    try {
        return new Intl.NumberFormat('en-US', {
            notation: 'compact',
            compactDisplay: 'short'
        }).format(value);
    } catch (e) {
        return value;
    }
};

// Format date helper
const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">Supplier Dashboard</h2>
                    <p class="text-sm text-gray-500 mt-1">Overview of your e-procurement marketplace performance and user signups.</p>
                </div>
                <div class="text-xs text-gray-400 bg-gray-100 px-3 py-1.5 rounded-full font-medium">
                    Real-time Summary
                </div>
            </div>
        </template>

        <div class="space-y-8">
            <!-- 1. Overview Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Total Products -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-[#1a2b4c]"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Total Products</p>
                            <h4 class="text-2xl xl:text-3xl font-extrabold text-[#1a2b4c] mt-2 whitespace-nowrap" :title="stats.total_products">{{ formatCompactNumber(stats.total_products) }}</h4>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl text-gray-500 group-hover:bg-[#1a2b4c]/5 group-hover:text-[#1a2b4c] transition-colors flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                        <Link :href="route('admin.products.index')" class="text-xs font-bold text-[#e96a25] hover:underline flex items-center space-x-1">
                            <span>Manage inventory</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>
                </div>

                <!-- Total Categories -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-[#e96a25]"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Categories</p>
                            <h4 class="text-2xl xl:text-3xl font-extrabold text-[#1a2b4c] mt-2 whitespace-nowrap" :title="stats.total_categories">{{ formatCompactNumber(stats.total_categories) }}</h4>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl text-gray-500 group-hover:bg-[#e96a25]/5 group-hover:text-[#e96a25] transition-colors flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                        <Link :href="route('admin.categories.index')" class="text-xs font-bold text-[#e96a25] hover:underline flex items-center space-x-1">
                            <span>Manage taxonomy</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Total Orders</p>
                            <h4 class="text-2xl xl:text-3xl font-extrabold text-[#1a2b4c] mt-2 whitespace-nowrap" :title="stats.total_orders">{{ formatCompactNumber(stats.total_orders) }}</h4>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl text-gray-500 group-hover:bg-emerald-50 group-hover:text-emerald-500 transition-colors flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <span class="text-xs text-gray-400 font-medium">From all active buyers</span>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-indigo-500"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Total Revenue</p>
                            <h4 class="text-2xl xl:text-3xl font-extrabold text-[#1a2b4c] mt-2 whitespace-nowrap tracking-tight" :title="formatCurrency(stats.total_revenue)">{{ formatCompactCurrency(stats.total_revenue) }}</h4>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl text-gray-500 group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-colors flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <span class="text-xs text-gray-400 font-medium">B2B submitted checkout value</span>
                    </div>
                </div>
            </div>

            <!-- 2. Interactive Performance Chart -->
            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-8 gap-6">
                    <div>
                        <h3 class="text-lg font-bold text-[#1a2b4c]">Performance Analytics</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Deep dive into transactional flow and categorization metrics.</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Pivot Mode Toggle (Value/Volume) -->
                        <div class="inline-flex bg-gray-100 p-1 rounded-xl border border-gray-100 shadow-sm mr-2">
                            <button 
                                @click="selectedMetric = 'total'"
                                :class="[
                                    'px-4 py-1.5 rounded-lg text-xs font-extrabold transition-all duration-300 flex items-center space-x-1.5',
                                    selectedMetric === 'total' ? 'bg-white text-[#e96a25] shadow-sm ring-1 ring-black/5' : 'text-gray-500 hover:text-[#1a2b4c]'
                                ]"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Value</span>
                            </button>
                            <button 
                                @click="selectedMetric = 'count'"
                                :class="[
                                    'px-4 py-1.5 rounded-lg text-xs font-extrabold transition-all duration-300 flex items-center space-x-1.5',
                                    selectedMetric === 'count' ? 'bg-white text-[#e96a25] shadow-sm ring-1 ring-black/5' : 'text-gray-500 hover:text-[#1a2b4c]'
                                ]"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                <span>Volume</span>
                            </button>
                        </div>

                        <div class="h-6 w-px bg-gray-200 mx-1 hidden md:block"></div>

                        <!-- Group By Dropdown -->
                        <div class="flex items-center space-x-2 bg-white border border-gray-200 rounded-xl px-3 py-2 shadow-sm hover:border-gray-300 transition-colors">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100 pr-2">By</span>
                            <select v-model="selectedGroupBy" class="bg-transparent border-0 p-0 text-xs font-bold text-[#1a2b4c] focus:ring-0 cursor-pointer appearance-none pr-7">
                                <option value="company">Company</option>
                                <option value="category">Category</option>
                            </select>
                        </div>

                        <!-- Premium Custom Month Picker -->
                        <div class="relative">
                            <!-- The Capsule Button -->
                            <button 
                                @click="openPicker"
                                type="button"
                                class="flex items-center space-x-2 bg-white border border-gray-200 rounded-xl px-3 py-1.5 shadow-sm hover:border-[#e96a25] transition-all min-w-[160px] text-left group"
                            >
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100 pr-2 shrink-0 group-hover:text-[#e96a25]">Period</span>
                                <span class="text-xs font-bold text-[#1a2b4c] flex-1">{{ displayPeriod }}</span>
                                <!-- Simple Chevron Icon -->
                                <svg class="w-3 h-3 text-gray-400 group-hover:text-[#e96a25] transition-transform" :class="{'rotate-180': isPickerOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <!-- Transparent Click-Outside Overlay -->
                            <div v-if="isPickerOpen" @click="isPickerOpen = false" class="fixed inset-0 z-40"></div>

                            <!-- The Floating Premium Picker Card -->
                            <div v-if="isPickerOpen" class="absolute right-0 mt-2 w-64 bg-white border border-gray-100 rounded-2xl shadow-2xl z-50 p-4 origin-top-right transform transition-all duration-200 ease-out scale-100 opacity-100">
                                 <!-- Header: Year Nav -->
                                 <div class="flex items-center justify-between mb-4 border-b border-gray-50 pb-2">
                                     <button type="button" @click.stop="pickerYear--" class="p-1.5 hover:bg-gray-100 rounded-full text-gray-500 hover:text-[#e96a25] transition-colors">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                     </button>
                                     <span class="text-sm font-black text-[#1a2b4c] tracking-wide">{{ pickerYear }}</span>
                                     <button type="button" @click.stop="pickerYear++" class="p-1.5 hover:bg-gray-100 rounded-full text-gray-500 hover:text-[#e96a25] transition-colors">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                     </button>
                                 </div>
                                 <!-- Grid: Months -->
                                 <div class="grid grid-cols-3 gap-2">
                                     <button 
                                        v-for="(m, i) in monthNames" 
                                        :key="i"
                                        type="button"
                                        @click.stop="selectMonth(i+1)"
                                        :class="[
                                            'py-2 text-xs font-bold rounded-lg transition-all border',
                                            filterPeriod === `${pickerYear}-${String(i+1).padStart(2,'0')}` 
                                               ? 'bg-[#e96a25] text-white border-[#e96a25] shadow-md shadow-orange-200'
                                               : 'text-gray-600 bg-white border-transparent hover:border-orange-100 hover:bg-orange-50 hover:text-[#e96a25]'
                                        ]"
                                     >{{ m }}</button>
                                 </div>
                                 
                                 <!-- Clear Option -->
                                 <div class="mt-3 pt-2 border-t border-gray-50 text-center">
                                     <button 
                                        type="button" 
                                        @click.stop="filterPeriod = ''; isPickerOpen = false;"
                                        class="text-[10px] font-bold text-gray-400 hover:text-[#e96a25] uppercase tracking-wider"
                                     >
                                         Clear Filter
                                     </button>
                                 </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- High-Contrast Visual Legend Block -->
                <div v-if="uniqueSegments.length > 0" class="flex flex-wrap items-center gap-x-4 gap-y-2 mb-6 bg-gray-50/60 border border-gray-100 p-3.5 rounded-xl animate-fade-in">
                    <div class="flex items-center space-x-2 text-[10px] font-black text-gray-400 uppercase tracking-[0.15em] mr-2 shrink-0">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                        <span>Key:</span>
                    </div>
                    <div v-for="lbl in uniqueSegments" :key="lbl" class="flex items-center space-x-2 group bg-white px-2 py-1 rounded-lg shadow-sm border border-gray-100 hover:border-gray-200 transition-all duration-200">
                        <div :class="['w-2.5 h-2.5 rounded bg-gradient-to-br shadow-sm group-hover:scale-110 transition-transform', getColorForSegment(lbl)]"></div>
                        <span class="text-[11px] font-bold text-[#1a2b4c] opacity-80 group-hover:opacity-100">{{ lbl }}</span>
                    </div>
                </div>

                <div v-if="chartData && chartData.length > 0" class="h-80 flex items-end justify-between space-x-4 pt-12 px-2 border-b border-gray-100 overflow-x-auto pb-6 scrollbar-thin">
                    <div 
                        v-for="data in chartData" 
                        :key="data.label"
                        class="flex-1 min-w-[65px] flex flex-col items-center group relative h-full justify-end"
                    >
                        <!-- Enhanced Dynamic Matrix Tooltip -->
                        <div class="absolute bottom-full mb-3 bg-[#1a2b4c] text-white text-xs px-3 py-2.5 rounded-xl opacity-0 pointer-events-none group-hover:opacity-100 transition-all duration-200 shadow-2xl z-20 min-w-[150px] transform translate-y-2 group-hover:translate-y-0 backdrop-blur-sm ring-1 ring-white/10">
                            <div class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 rotate-45 w-2.5 h-2.5 bg-[#1a2b4c]"></div>
                            <p class="font-black text-gray-300 text-[9px] uppercase tracking-widest border-b border-white/10 pb-1.5 mb-2 text-center">{{ data.label }}</p>
                            
                            <!-- Detailed Matrix Breakdown List -->
                            <div class="space-y-1.5 max-h-[180px] overflow-y-auto scrollbar-none">
                                <div 
                                    v-for="seg in data.segments" 
                                    :key="seg.label"
                                    class="flex items-center justify-between gap-4 text-[10px]"
                                >
                                    <div class="flex items-center space-x-1.5 max-w-[80px]">
                                        <div :class="['w-1.5 h-1.5 rounded-full bg-gradient-to-br', getColorForSegment(seg.label)]"></div>
                                        <span class="text-gray-300 truncate leading-tight">{{ seg.label }}</span>
                                    </div>
                                    <span class="font-bold text-white whitespace-nowrap">
                                        {{ selectedMetric === 'total' ? formatCurrency(seg.total) : seg.count }}
                                    </span>
                                </div>
                            </div>

                            <!-- Overall Sum Footer -->
                            <div class="mt-2 pt-1.5 border-t border-white/10 flex items-center justify-between font-extrabold text-[11px]">
                                <span class="text-[#e96a25]">Total</span>
                                <span class="text-white">
                                    {{ selectedMetric === 'total' ? formatCurrency(data.overallTotal) : data.overallCount + ' Units' }}
                                </span>
                            </div>
                        </div>

                        <!-- Animated Scalable COMPOUND Stacked Bar -->
                        <div 
                            class="w-full max-w-[40px] rounded-t-lg shadow-sm hover:shadow-md transition-all duration-700 ease-out hover:brightness-110 origin-bottom cursor-pointer flex flex-col-reverse overflow-hidden relative group-hover:ring-2 ring-offset-2 ring-[#e96a25]/20"
                            :style="{ height: `${Math.max(6, (Number(data[currentMaxKey]) / maxVal) * 100)}%` }"
                        >
                            <!-- Iterate nested segment nodes with proportional height inside stack container -->
                            <div 
                                v-for="(seg, sidx) in data.segments"
                                :key="seg.label"
                                class="w-full transition-all duration-500 ease-in-out relative"
                                :class="[
                                    'bg-gradient-to-t',
                                    getColorForSegment(seg.label),
                                    sidx === 0 ? 'border-t border-white/20' : 'border-b border-black/5'
                                ]"
                                :style="{ 
                                    height: `${(Number(seg[selectedMetric === 'total' ? 'total' : 'count']) / Math.max(1, Number(data[currentMaxKey]))) * 100}%` 
                                }"
                                :title="`${seg.label}: ${selectedMetric === 'total' ? formatCurrency(seg.total) : seg.count}`"
                            >
                                <!-- Glass overlay layer -->
                                <div class="absolute inset-0 bg-white/5 pointer-events-none group-hover:bg-transparent"></div>
                            </div>
                        </div>

                        <!-- Axis Label -->
                        <span 
                            class="text-[10px] font-bold text-gray-400 mt-4 text-center max-w-[90px] truncate whitespace-nowrap block overflow-hidden w-full leading-tight group-hover:text-[#1a2b4c] transition-colors"
                            :title="data.label"
                        >
                            {{ data.label }}
                        </span>
                    </div>
                </div>
                <div v-else class="h-72 flex flex-col items-center justify-center border border-dashed border-gray-200 rounded-xl bg-gray-50">
                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="text-sm text-gray-500 font-semibold mt-2">No metrics available for the current selection</p>
                </div>
            </div>

            <!-- 3. Split Screen Tables (Submitted Orders & New Registered Customers) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left: Submitted Orders -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm flex flex-col h-[500px]">
                    <div class="flex items-center justify-between mb-4 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-[#1a2b4c]">Submitted Orders</h3>
                            <p class="text-xs text-gray-500">Complete list of submitted procurement requests.</p>
                        </div>
                        <span class="px-2.5 py-1 bg-[#1a2b4c]/5 text-[#1a2b4c] text-xs font-bold rounded-full">
                            {{ orders.length }} Total
                        </span>
                    </div>

                    <div class="flex-grow overflow-y-auto border border-gray-100 rounded-xl scrollbar-thin">
                        <table v-if="orders && orders.length > 0" class="min-w-full divide-y divide-gray-100 text-left">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Order ID</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Company</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Submitted By</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Total</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                <tr v-for="order in orders" :key="order.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3.5 text-sm font-bold text-gray-900">#{{ order.id }}</td>
                                    <td class="px-4 py-3.5 text-sm text-gray-600">{{ order.company?.name || 'N/A' }}</td>
                                    <td class="px-4 py-3.5 text-sm text-gray-600">{{ order.user?.name || 'N/A' }}</td>
                                    <td class="px-4 py-3.5 text-sm font-extrabold text-[#e96a25]">{{ formatCurrency(order.total) }}</td>
                                    <td class="px-4 py-3.5 text-xs text-gray-500">{{ formatDate(order.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="h-full flex flex-col items-center justify-center p-8 text-center text-gray-400">
                            <svg class="w-12 h-12 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v2m16 4h-2a2 2 0 00-2 2v1a2 2 0 002 2h2a2 2 0 002-2v-1a2 2 0 00-2-2z" />
                            </svg>
                            <p class="text-sm font-medium mt-2">No orders submitted yet</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Registered Customers -->
                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm flex flex-col h-[500px]">
                    <div class="flex items-center justify-between mb-4 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-[#1a2b4c]">Registered Customers</h3>
                            <p class="text-xs text-gray-500">Newly registered active buyer customer accounts.</p>
                        </div>
                        <span class="px-2.5 py-1 bg-[#e96a25]/5 text-[#e96a25] text-xs font-bold rounded-full">
                            {{ customers.length }} Active
                        </span>
                    </div>

                    <div class="flex-grow overflow-y-auto border border-gray-100 rounded-xl scrollbar-thin">
                        <table v-if="customers && customers.length > 0" class="min-w-full divide-y divide-gray-100 text-left">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Company Name</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Company Address</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase text-gray-500 tracking-wider">Joined Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                <tr v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3.5 text-sm font-semibold text-gray-900">{{ customer.name }}</td>
                                    <td class="px-4 py-3.5 text-sm text-gray-600">{{ customer.email }}</td>
                                    <td class="px-4 py-3.5 text-sm text-gray-600 font-medium">{{ customer.company?.name || 'Self-Registered' }}</td>
                                    <td class="px-4 py-3.5 text-xs text-gray-500 italic max-w-xs truncate" :title="customer.company?.address">{{ customer.company?.address || 'N/A' }}</td>
                                    <td class="px-4 py-3.5 text-xs text-gray-500">{{ formatDate(customer.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="h-full flex flex-col items-center justify-center p-8 text-center text-gray-400">
                            <svg class="w-12 h-12 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p class="text-sm font-medium mt-2">No registered customers yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
