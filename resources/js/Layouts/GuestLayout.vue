<script setup>
import { Link } from '@inertiajs/vue3';
import Footer from '@/Components/Footer.vue';
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col justify-between text-gray-800">
        <!-- Main Content Area -->
        <div class="flex-grow flex flex-col items-center justify-center px-4 pt-12 pb-24 sm:px-6 lg:px-8">
            <!-- Branding Header -->
            <div class="mb-8 text-center animate-fade-in">
                <Link :href="route('catalog.index')" class="flex flex-col items-center space-y-2">
                    <span class="font-black text-5xl tracking-tighter text-[#1a2b4c] italic hover:scale-105 transition-transform">M</span>
                    <div class="flex flex-col leading-none">
                        <span class="font-bold text-lg tracking-widest text-[#1a2b4c]">MODERN</span>
                        <span class="font-light text-xs tracking-widest text-gray-500 mt-1">E-PROCUREMENT PORTAL</span>
                    </div>
                </Link>
            </div>

            <!-- Card Container -->
            <div class="w-full max-w-md bg-white border border-gray-100 rounded-2xl shadow-xl shadow-slate-200/50 p-8 transition-all hover:shadow-2xl hover:shadow-slate-200/60 duration-300">
                <slot />
            </div>
        </div>

        <!-- Uniform Footer -->
        <Footer />
    </div>
</template>
