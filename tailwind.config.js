import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Colors emitted dynamically from EquipmentType::color() in the
    // topology filter dropdown. Tailwind can't see these in the content
    // scan; keep them safelisted so the pallini colorati survive purge.
    safelist: [
        'bg-cyan-500', 'bg-violet-500', 'bg-red-500', 'bg-emerald-500',
        'bg-amber-500', 'bg-slate-500', 'bg-stone-500', 'bg-blue-500',
        'bg-yellow-500', 'bg-fuchsia-500', 'bg-teal-500', 'bg-orange-500',
        'bg-indigo-500', 'bg-lime-500', 'bg-sky-500', 'bg-pink-500',
        'bg-rose-500', 'bg-gray-500',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
