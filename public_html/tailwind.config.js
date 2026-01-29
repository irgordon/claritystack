/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
    "./themes/**/*.{php,html}", // Scan CMS Theme files
    "./api/**/*.{php}"          // Scan Email Templates & backend views
  ],
  theme: {
    extend: {
      colors: {
        // Map Tailwind utility classes to our CSS variables
        brand: {
          primary: 'var(--primary-brand)',
          secondary: 'var(--secondary-brand)',
        }
      },
      fontFamily: {
        heading: ['var(--font-heading)', 'sans-serif'],
        body: ['var(--font-body)', 'sans-serif'],
      },
      borderRadius: {
        DEFAULT: 'var(--border-radius)',
      }
    },
  },
  plugins: [
    require('@tailwindcss/typography'), // For CMS text blocks
    require('@tailwindcss/forms'),      // For better default inputs
  ],
}
