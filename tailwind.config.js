/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.php",
    "./result.php",
    "./config.php",
    "./admin/**/*.{php,html,js}",
    "./common/**/*.{php,html,js}",
    "./user/**/*.{php,html,js}",
    "./result/**/*.{php,html,js}",
    "./assets/**/*.{php,html,js}"
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          400: '#38bdf8',
          500: '#0ea5e9',
          600: '#0284c7',
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      }
    },
  },
  plugins: [],
};

