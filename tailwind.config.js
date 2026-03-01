/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './templates/**/*.twig',
        './src/**/*.php',
        './assets/**/*.js',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
