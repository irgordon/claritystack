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
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/forms'),
  ],
}
