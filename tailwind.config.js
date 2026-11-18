// tailwind.config.js
module.exports = {
    darkMode: 'class', // Manual toggle via .dark-mode class
    content: [
        './public/**/*.php',
        './public/**/*.html',
        './public/**/*.js',
        './public/components/**/*.php',
        './public/pages/**/*.php',
    ],
    safelist: [
        'shadow-[var(--clr-card-shadow)]',
        'border-[var(--clr-card-border)]',
        'hover:bg-[var(--clr-primary-hover)]',
        'hover:bg-[var(--clr-button-hover)]',
        'bg-[var(--clr-card-border)]',
        'bg-[var(--clr-bg)]',
        'border-t-[var(--clr-card-border)]',
        'border-b-[var(--clr-card-border)]',
        'hover:text-[var(--clr-text-secondary)]',

        // add any other arbitrary classes used in JS
    ],
    theme: {
        extend: {
            colors: {
                // Map CSS variables to Tailwind for easy use
                primary: 'var(--clr-primary)',
                'primary-hover': 'var(--clr-primary-hover)',
                bg: 'var(--clr-bg)',
                'card-bg': 'var(--clr-card-bg)',
                'card-border': 'var(--clr-card-border)',
                text: 'var(--clr-text)',
                'text-secondary': 'var(--clr-text-secondary)',
                'input-bg': 'var(--clr-input-bg)',
                'input-border': 'var(--clr-input-border)',
                'input-focus': 'var(--clr-input-focus)',
                success: 'var(--clr-success)',
                warning: 'var(--clr-warning)',
                danger: 'var(--clr-danger)',
                'footer-bg': 'var(--clr-footer-bg)',
                'footer-text': 'var(--clr-footer-text)',
                link: 'var(--clr-link)',
                'link-hover': 'var(--clr-link-hover)',
            },
        },
    },
    plugins: [],
};