/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
    "./clarity_app/themes/**/*.{php,html}",
    "./clarity_app/api/**/*.{php}"
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          teal: '#0e8966',
          cyan: '#94e5e5',
          salmon: '#fa9680',
          dark: '#1a202c',
          primary: 'var(--primary-brand)',
          secondary: 'var(--secondary-brand)',
        }
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        heading: ['var(--font-heading)', 'sans-serif'],
        body: ['var(--font-body)', 'sans-serif'],
      },
      borderRadius: {
        DEFAULT: 'var(--border-radius)',
      }
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/forms'),
  ],
}
