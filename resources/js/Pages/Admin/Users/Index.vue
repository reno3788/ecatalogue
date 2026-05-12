<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Array,
    companies: Array,
    roles: Array,
});

const editingUser = ref(null);

const form = useForm({
    company_id: '',
    role: '',
});

const startEdit = (user) => {
    editingUser.value = user.id;
    form.company_id = user.company_id || '';
    form.role = user.roles && user.roles[0] ? user.roles[0].name : 'user';
};

const cancelEdit = () => {
    editingUser.value = null;
};

const submitUpdate = (userId) => {
    form.put(route('admin.users.update', userId), {
        onSuccess: () => {
            editingUser.value = null;
        }
    });
};

const deleteUser = (userId) => {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        useForm({}).delete(route('admin.users.destroy', userId));
    }
};
</script>

<template>
    <Head title="User Configuration" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-[#1a2b4c] leading-tight">User Configuration</h2>
                    <p class="text-sm text-gray-500 mt-1">Configure user roles, company affiliations, and permissions.</p>
                </div>
            </div>
        </template>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                <table class="min-w-full divide-y divide-gray-100 text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">User Info</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Role</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Assigned Company</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider">Joined Date</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase text-gray-500 tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 bg-white">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition-colors">
                            <!-- User Info -->
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#1a2b4c] to-[#e96a25] flex items-center justify-center text-white font-extrabold text-sm uppercase">
                                        {{ user.name.substring(0, 2) }}
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-sm">{{ user.name }}</h4>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="px-6 py-4">
                                <div v-if="editingUser === user.id">
                                    <select 
                                        v-model="form.role" 
                                        class="text-xs rounded-md border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-1"
                                    >
                                        <option v-for="role in roles" :key="role.id" :value="role.name">
                                            {{ role.name.toUpperCase() }}
                                        </option>
                                    </select>
                                </div>
                                <div v-else>
                                    <span 
                                        :class="[
                                            'px-2.5 py-1 text-xs font-extrabold rounded-full',
                                            user.roles && user.roles[0]?.name === 'admin' 
                                                ? 'bg-rose-50 text-rose-600 border border-rose-100' 
                                                : 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                        ]"
                                    >
                                        {{ user.roles && user.roles[0] ? user.roles[0].name.toUpperCase() : 'USER' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Company -->
                            <td class="px-6 py-4">
                                <div v-if="editingUser === user.id">
                                    <select 
                                        v-model="form.company_id" 
                                        class="text-xs rounded-md border-gray-200 focus:border-[#e96a25] focus:ring focus:ring-[#e96a25]/20 py-1 max-w-[180px]"
                                    >
                                        <option value="">No Company</option>
                                        <option v-for="company in companies" :key="company.id" :value="company.id">
                                            {{ company.name }}
                                        </option>
                                    </select>
                                </div>
                                <div v-else>
                                    <span class="text-sm text-gray-600 font-medium">
                                        {{ user.company?.name || 'Self-Registered' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Joined Date -->
                            <td class="px-6 py-4 text-xs text-gray-400 font-medium">
                                {{ new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div v-if="editingUser === user.id" class="flex items-center justify-end space-x-2">
                                    <button 
                                        @click="submitUpdate(user.id)" 
                                        class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-xs font-bold transition-colors"
                                    >
                                        Save
                                    </button>
                                    <button 
                                        @click="cancelEdit" 
                                        class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-semibold transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
                                <div v-else class="flex items-center justify-end space-x-3">
                                    <button 
                                        @click="startEdit(user)" 
                                        class="p-1.5 text-gray-400 hover:text-[#1a2b4c] transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                    </button>
                                    <button 
                                        v-if="$page.props.auth.user.id !== user.id"
                                        @click="deleteUser(user.id)" 
                                        class="p-1.5 text-gray-400 hover:text-red-500 transition"  title="Delete User">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                         </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
