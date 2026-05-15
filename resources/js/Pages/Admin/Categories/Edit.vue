<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';

const props = defineProps({
    category: {
        type: Object,
        default: () => ({
            name: '',
        })
    },
    categories: Array,
});

const isEditing = !!props.category.id;

const form = useForm({
    name: props.category.name,
    parent_id: props.category.parent_id || null,
});

const submit = () => {
    if (isEditing) {
        form.put(route('admin.categories.update', props.category.id));
    } else {
        form.post(route('admin.categories.store'));
    }
};

const breadcrumbItems = [
    { label: 'Dashboard', href: route('admin.dashboard'), icon: 'dashboard' },
    { label: 'Categories', href: route('admin.categories.index') },
];
</script>

<template>
    <Head :title="isEditing ? 'Edit Category' : 'Add Category'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <h2 class="font-extrabold text-2xl text-gray-900 leading-tight">
                    {{ isEditing ? 'Edit Category' : 'Add Category' }}
                </h2>
                <Breadcrumbs :items="breadcrumbItems" />
            </div>
        </template>

        <div class="py-1">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category Name</label>
                            <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]" required>
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Parent Category (Optional)</label>
                            <select v-model="form.parent_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[#e96a25] focus:ring-[#e96a25]">
                                <option :value="null">None (Root Category)</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <div v-if="form.errors.parent_id" class="text-red-500 text-xs mt-1">{{ form.errors.parent_id }}</div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-[#e96a25] hover:bg-[#d55e1d] text-white px-4 py-2 rounded text-sm font-bold" :disabled="form.processing">
                                {{ isEditing ? 'Update Category' : 'Create Category' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
