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
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                unt: {
                    azul: '#12377B',
                    'azul-oscuro': '#092C6C',
                    noche: '#0B1F44',
                    dorado: '#E6AD09',
                    'dorado-oscuro': '#BD8E07',
                    'dorado-texto': '#8A6700',
                    negro: '#1B1811',
                    'fondo-suave': '#F5F7FA',
                    papel: '#F7F3EA',
                    texto: '#222222',
                    'texto-suave': '#5F6670',
                    borde: '#E3E6EA',
                },
            },
            boxShadow: {
                'unt-card': '0 2px 10px rgba(0, 0, 0, 0.08)',
                'unt-hover': '0 8px 24px rgba(18, 55, 123, 0.18)',
            },
        },
    },

    plugins: [forms],
};
