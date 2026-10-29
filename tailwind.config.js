import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // Identitas warna Pelangi Elektronik. Kelas lama (indigo/slate/emerald/amber/red) otomatis memakai palet ini.
            colors: {
                indigo: {
                    50: '#EEF5FE', 100: '#DCEAFD', 200: '#BCD6FB', 300: '#8FBBF9', 400: '#5E9DF8',
                    500: '#3987F7', 600: '#1769E0', 700: '#1257B8', 800: '#12468F', 900: '#12315A', 950: '#0C2240',
                },
                slate: {
                    50: '#F8FAFD', 100: '#F4F7FC', 200: '#E3EAF5', 300: '#CBD6E6', 400: '#9BA9BF',
                    500: '#738198', 600: '#55627A', 700: '#3A4963', 800: '#172B4D', 900: '#12315A', 950: '#0C2240',
                },
                emerald: { 50: '#E9F7F1', 100: '#CFEEDF', 200: '#A0DCC2', 500: '#26B07F', 600: '#159A68', 700: '#0F7B54' },
                amber: { 50: '#FEF6E4', 100: '#FCEBC2', 200: '#F8D78A', 500: '#E7A323', 600: '#C98A12', 700: '#9C6A0C' },
                red: { 50: '#FDEDED', 100: '#FAD6D6', 200: '#F2A8A8', 500: '#D94242', 600: '#C13030', 700: '#9E2525' },
            },
            fontFamily: {
                sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'SF Pro Text', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: { lg: '12px', xl: '16px', '2xl': '22px' },
            boxShadow: {
                sm: '0 1px 2px rgba(18,49,90,.06), 0 1px 3px rgba(18,49,90,.05)',
                md: '0 4px 14px rgba(18,49,90,.08)',
                lg: '0 10px 30px rgba(18,49,90,.12)',
            },
            transitionDuration: { DEFAULT: '200ms' },
        },
    },

    plugins: [forms],
};
