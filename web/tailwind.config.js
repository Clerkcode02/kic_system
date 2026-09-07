/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // Trade Docket world (freelancer area) — kraft ticket stock, ruled
        // ledger structure, hand-stamped status ink. Scoped to the freelance
        // feature; the rest of the app keeps stock Tailwind gray/blue.
        docket: {
          paper: '#EDE4D2',
          well: '#E2D5B8',
          ink: '#221C14',
          soft: '#5C5442',
          rule: '#C7B996',
          line: '#A6976F',
        },
        stamp: {
          paid: '#146B3D',
          'paid-tint': '#E1F1E6',
          awaiting: '#8A5A00',
          'awaiting-tint': '#FAECD6',
          active: '#154E8C',
          'active-tint': '#E1EDF7',
          disputed: '#9A2E22',
          'disputed-tint': '#FAE6E1',
          neutral: '#54503F',
          'neutral-tint': '#E9E2CE',
        },
        action: {
          DEFAULT: '#123B33',
          hover: '#0B2A24',
          tint: '#DFE9E6',
        },
      },
      keyframes: {
        'stamp-land': {
          '0%': { transform: 'scale(1.6) rotate(-10deg)', opacity: '0' },
          '60%': { transform: 'scale(0.94) rotate(-2deg)', opacity: '1' },
          '100%': { transform: 'scale(1) rotate(-2deg)', opacity: '1' },
        },
      },
      animation: {
        'stamp-land': 'stamp-land 260ms cubic-bezier(0.16, 1, 0.3, 1) both',
      },
      boxShadow: {
        ticket: '0 1px 0 rgba(34,28,20,0.08), 0 10px 18px -14px rgba(34,28,20,0.45)',
      },
    },
  },
  plugins: [],
}
