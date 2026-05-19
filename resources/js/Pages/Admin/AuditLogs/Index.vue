<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array
});

const search = ref(props.filters.search || '');
const eventFilter = ref(props.filters.event || '');
const userFilter = ref(props.filters.user_id || '');
const startDateFilter = ref(props.filters.start_date || '');
const endDateFilter = ref(props.filters.end_date || '');
const expandedLogId = ref(null);

const toggleExpand = (id) => {
    expandedLogId.value = expandedLogId.value === id ? null : id;
};

let timeout = null;
const applyFilters = () => {
    router.get(route('admin.audit-logs.index'), {
        search: search.value,
        event: eventFilter.value,
        user_id: userFilter.value,
        start_date: startDateFilter.value,
        end_date: endDateFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 400);
});

watch([eventFilter, userFilter, startDateFilter, endDateFilter], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    eventFilter.value = '';
    userFilter.value = '';
    startDateFilter.value = '';
    endDateFilter.value = '';
};

// Stylistic Event Badges configuration
const getEventStyle = (event) => {
    switch (event) {
        case 'created':
            return 'bg-emerald-50 text-emerald-700 border-emerald-100 ring-emerald-500/10';
        case 'updated':
            return 'bg-sky-50 text-sky-700 border-sky-100 ring-sky-500/10';
        case 'deleted':
            return 'bg-rose-50 text-rose-700 border-rose-100 ring-rose-500/10';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-100 ring-gray-500/10';
    }
};

const getEventLabel = (event) => {
    switch (event) {
        case 'created': return 'Created';
        case 'updated': return 'Updated';
        case 'deleted': return 'Deleted';
        default: return event;
    }
};

const getEventIcon = (event) => {
    switch (event) {
        case 'created':
            return `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>`;
        case 'updated':
            return `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 19M3 9h6"/></svg>`;
        case 'deleted':
            return `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`;
        default:
            return `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
    }
};
</script>

<template>
    <Head title="Audit Trail Logs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">System Audit Trail</h2>
                    <p class="text-sm text-gray-500 mt-1">Comprehensive historical log of operations, data mutations, and system-wide administrative actions.</p>
                </div>
            </div>
        </template>

        <!-- Premium Glass Filter Card -->
        <div class="mb-6 bg-white p-5 rounded-2xl border border-gray-200/60 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-[#1a2b4c] uppercase tracking-wider mb-1.5">Search keywords</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Find users, IPs, or actions..." 
                            class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-[#e96a25] focus:border-[#e96a25] transition duration-150"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#1a2b4c] uppercase tracking-wider mb-1.5">Filter by User</label>
                    <select 
                        v-model="userFilter" 
                        class="w-full py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-[#e96a25] focus:border-[#e96a25] transition duration-150"
                    >
                        <option value="">All Users</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.email }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#1a2b4c] uppercase tracking-wider mb-1.5">Filter by Event</label>
                    <select 
                        v-model="eventFilter" 
                        class="w-full py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-[#e96a25] focus:border-[#e96a25] transition duration-150"
                    >
                        <option value="">All Events</option>
                        <option value="created">Created Only</option>
                        <option value="updated">Updated Only</option>
                        <option value="deleted">Deleted Only</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#1a2b4c] uppercase tracking-wider mb-1.5">Start Date</label>
                    <input 
                        v-model="startDateFilter" 
                        type="date" 
                        class="w-full py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-[#e96a25] focus:border-[#e96a25] transition duration-150 cursor-pointer"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#1a2b4c] uppercase tracking-wider mb-1.5">End Date</label>
                    <input 
                        v-model="endDateFilter" 
                        type="date" 
                        class="w-full py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:ring-[#e96a25] focus:border-[#e96a25] transition duration-150 cursor-pointer"
                    />
                </div>

                <div>
                    <button 
                        v-if="search || eventFilter || userFilter || startDateFilter || endDateFilter"
                        @click="clearFilters" 
                        class="w-full py-2 px-4 text-sm font-semibold text-gray-500 bg-gray-50 border border-gray-200 rounded-xl hover:bg-gray-100 hover:text-gray-700 transition duration-150"
                    >
                        Clear Filters
                    </button>
                    <div v-else class="text-xs text-gray-400 italic text-center pb-2">
                        Showing latest logs
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Event List / Table View -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div v-if="logs.data.length === 0" class="p-16 text-center">
                <div class="w-16 h-16 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="font-bold text-lg text-[#1a2b4c]">No Audit Logs Recorded</h3>
                <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">No operational footprints match the parameters chosen. Adjust your filters or check back as data grows.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-[#1a2b4c] uppercase tracking-wider">User / Actor</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-[#1a2b4c] uppercase tracking-wider">Event</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-[#1a2b4c] uppercase tracking-wider">Target Object</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-[#1a2b4c] uppercase tracking-wider">IP Address</th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-[#1a2b4c] uppercase tracking-wider">Date & Time</th>
                            <th scope="col" class="relative px-6 py-3.5 w-10">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template v-for="log in logs.data" :key="log.id">
                            <!-- Main Row -->
                            <tr 
                                @click="toggleExpand(log.id)"
                                class="hover:bg-gray-50/70 transition duration-150 cursor-pointer select-none"
                                :class="{ 'bg-orange-50/10': expandedLogId === log.id }"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <!-- Mini Accent Badge for event type inside user avatar position -->
                                        <div 
                                            class="w-8 h-8 rounded-full flex items-center justify-center border shadow-xs flex-shrink-0"
                                            :class="[
                                                log.event === 'created' ? 'bg-emerald-50 border-emerald-100 text-emerald-600' : '',
                                                log.event === 'updated' ? 'bg-sky-50 border-sky-100 text-sky-600' : '',
                                                log.event === 'deleted' ? 'bg-rose-50 border-rose-100 text-rose-600' : ''
                                            ]"
                                            v-html="getEventIcon(log.event)"
                                        ></div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-900">
                                                {{ log.user ? log.user.name : 'System Automatic' }}
                                            </div>
                                            <div class="text-xs text-gray-500" v-if="log.user">
                                                {{ log.user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border shadow-sm"
                                        :class="getEventStyle(log.event)"
                                    >
                                        {{ getEventLabel(log.event) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-[#e96a25] uppercase tracking-wider">
                                            {{ log.auditable_type }}
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800 truncate max-w-xs mt-0.5" :title="log.target_name">
                                            {{ log.target_name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-700">
                                        {{ log.ip_address || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ log.time_ago }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        {{ log.created_at }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button 
                                        class="text-gray-400 hover:text-[#e96a25] transition duration-150 focus:outline-none"
                                        @click.stop="toggleExpand(log.id)"
                                    >
                                        <svg 
                                            class="w-5 h-5 transition-transform duration-200"
                                            :class="{ 'rotate-180 text-[#e96a25]': expandedLogId === log.id }"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>

                            <!-- Expandable Differential Details Row -->
                            <tr v-if="expandedLogId === log.id" class="bg-gray-50/40">
                                <td colspan="6" class="px-8 py-5 border-t border-gray-100 shadow-inner">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                        <div class="bg-white p-4 rounded-xl border border-gray-200/60 shadow-sm">
                                            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"/></svg>
                                                Performing Identity
                                            </div>
                                            <div v-if="log.user">
                                                <div class="text-sm font-bold text-gray-900">{{ log.user.name }}</div>
                                                <div class="text-xs text-gray-500">{{ log.user.email }}</div>
                                            </div>
                                            <div v-else class="text-sm italic text-gray-500">Background Scheduler</div>
                                        </div>

                                        <div class="bg-white p-4 rounded-xl border border-gray-200/60 shadow-sm">
                                            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                Physical Target
                                            </div>
                                            <div class="text-sm font-bold text-gray-900">{{ log.auditable_type }}</div>
                                            <div class="text-xs text-gray-500">Primary ID: #{{ log.auditable_id }}</div>
                                        </div>

                                        <div class="bg-white p-4 rounded-xl border border-gray-200/60 shadow-sm">
                                            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                                Connection Metadata
                                            </div>
                                            <div class="text-sm font-bold text-gray-900 truncate" :title="log.user_agent">{{ log.ip_address || 'N/A' }}</div>
                                            <div class="text-xs text-gray-400 font-mono truncate" :title="log.user_agent">{{ log.user_agent || 'No Agent' }}</div>
                                        </div>
                                    </div>

                                    <!-- Key-Value Side-by-Side Comparison Table -->
                                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                        <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                                            <span class="text-xs font-bold text-[#1a2b4c] uppercase tracking-wider">Attribute Differential Detail</span>
                                            <span class="text-[10px] text-gray-400 font-mono">{{ log.url }}</span>
                                        </div>
                                        
                                        <div v-if="log.event === 'updated'" class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-100">
                                                <thead class="bg-gray-50/50">
                                                    <tr>
                                                        <th scope="col" class="px-4 py-2 text-left text-[10px] font-bold uppercase text-gray-400 tracking-wider">Field Key</th>
                                                        <th scope="col" class="px-4 py-2 text-left text-[10px] font-bold uppercase text-gray-400 tracking-wider">Original Value</th>
                                                        <th scope="col" class="px-4 py-2 text-left text-[10px] font-bold uppercase text-gray-400 tracking-wider">Transitioned To</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr v-for="(val, key) in log.new_values" :key="key" class="hover:bg-gray-50/50">
                                                        <td class="px-4 py-3 text-sm font-mono font-bold text-gray-600 bg-gray-50/30 w-1/4">{{ key }}</td>
                                                        <td class="px-4 py-3 text-sm text-rose-700 bg-rose-50/20 w-3/8">
                                                            <span class="line-through font-mono text-xs break-all">
                                                                {{ typeof log.old_values[key] === 'object' ? JSON.stringify(log.old_values[key]) : (log.old_values[key] ?? 'NULL') }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-emerald-700 font-semibold bg-emerald-50/20 w-3/8">
                                                            <span class="font-mono text-xs break-all">
                                                                {{ typeof val === 'object' ? JSON.stringify(val) : (val ?? 'NULL') }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div v-else class="p-4">
                                            <div class="text-xs font-bold text-gray-400 mb-2">
                                                {{ log.event === 'created' ? 'Created Object Record:' : 'Destroyed Object Record Backup:' }}
                                            </div>
                                            <pre class="bg-gray-900 text-emerald-400 p-4 rounded-lg font-mono text-xs overflow-x-auto border border-gray-800 shadow-inner">{{ log.event === 'created' ? log.new_values : log.old_values }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fully integrated dynamic Pagination component footer -->
        <div class="mt-6">
            <Pagination :links="logs.links" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Modern aesthetic refinements for fine timeline tracking */
.w-3\/8 {
    width: 37.5%;
}
</style>
