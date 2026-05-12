import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                industrial: {
                    bg: '#F5F5F5',
                    surface: '#FFFFFF',
                    border: '#D1D5DB', // slate-300
                    text: '#111827', // gray-900
                    muted: '#6B7280', // gray-500
                    accent: '#FF5A00', // Safety Orange
                    accentHover: '#E04E00',
                }
            },
            boxShadow: {
                'industrial': '4px 4px 0px 0px rgba(0,0,0,1)',
                'industrial-sm': '2px 2px 0px 0px rgba(0,0,0,1)',
            }
        },
    },

    plugins: [forms],
};
