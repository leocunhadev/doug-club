import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                paper: '#F6F3EE',
                ink: '#1A1A1C',
                black: '#0B0B0C',
                card: '#FFFFFF',
                sand: '#E6E0D6',
                stone: '#8B857A',
                brand: '#FF5100',
                'brand-soft': '#FFEDE4',
            },
            fontFamily: {
                display: ['Syne', ...defaultTheme.fontFamily.sans],
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
