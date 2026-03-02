module.exports = {
	content: ['./assets/react/**/*.{js,jsx,ts,tsx}'],
	theme: {
		extend: {
			colors: {
				primary: '#D7FF01',
			},
			fontWeight: {
				w_regular: '400',
				w_medium: '500',
				w_semiBold: '600',
				w_bold: '700',
			},
			transitionTimingFunction: {
				DEFAULT: 'ease-in-out',
			},
			transitionDuration: {
				DEFAULT: '200ms',
			},
			keyframes: {
				shake: {
					'10%, 90%': {
						transform: 'translate3d(-1px, 0, 0)',
					},
					'20%, 80%': {
						transform: 'translate3d(2px, 0, 0)',
					},
					'30%, 50%, 70%': {
						transform: 'translate3d(-4px, 0, 0)',
					},
					'40%, 60%': {
						transform: 'translate3d(4px, 0, 0)',
					},
				},
				fade: {
					from: { opacity: '0' },
					to: { opacity: '1' },
				},
				scaleIn: {
					from: { opacity: '0', transform: 'scale(0.9)' },
					to: { opacity: '1', transform: 'scale(1)' },
				},
				slideDown: {
					from: { transform: ' translateY(-15px)' },
					to: { transform: 'translateY(0)' },
				},
				slideUp: {
					from: { transform: ' translateY(100%)', opacity: '0' },
					to: { transform: 'translateY(0)', opacity: '1' },
				},
				removeDown: {
					from: { transform: 'translateY(0)', opacity: '1' },
					to: { transform: ' translateY(100%)', opacity: '0' },
				},
				mountIcon: {
					'0%': {
						opacity: '0',
						transform: 'scaleX(1) scaleY(0.6) translateX(0px) translateY(5px)',
					},
					'50%': { transform: 'scale(1.2)' },
					'100%': {
						opacity: '1',
						transform: 'scaleX(1) scaleY(1) translateX(0px) translateY(3px)',
					},
				},
				slideDownAndFade: {
					from: { opacity: '0', transform: 'translateY(-2px)' },
					to: { opacity: '1', transform: 'translateY(0)' },
				},
				slideLeftAndFade: {
					from: { opacity: '0', transform: 'translateX(2px)' },
					to: { opacity: '1', transform: 'translateX(0)' },
				},
				slideUpAndFade: {
					from: { opacity: '0', transform: 'translateY(2px)' },
					to: { opacity: '1', transform: 'translateY(0)' },
				},
				slideRightAndFade: {
					from: { opacity: '0', transform: 'translateX(-2px)' },
					to: { opacity: '1', transform: 'translateX(0)' },
				},
				appearance: {
					'0%': { opacity: '0' },
					'100%': { opacity: '1' },
				},
			},
			animation: {
				shake: 'shake .5s ease-in-out infinite',
				fade: 'fade .5s ease-in-out',
				scaleIn: 'scaleIn .35s ease-in-out',
				mountIcon: 'mountIcon .5s ease-in-out',
				slideDown: 'slideDown .35s ease-in-out',
				slideUp: 'slideUp .35s ease-in-out',
				removeDown: 'removeDown .35s ease-in-out',

				slideDownAndFade: 'slideDownAndFade 400ms cubic-bezier(0.16, 1, 0.3, 1)',
				slideLeftAndFade: 'slideLeftAndFade 400ms cubic-bezier(0.16, 1, 0.3, 1)',
				slideUpAndFade: 'slideUpAndFade 400ms cubic-bezier(0.16, 1, 0.3, 1)',
				slideRightAndFade: 'slideRightAndFade 400ms cubic-bezier(0.16, 1, 0.3, 1)',
				appearance: 'appearance 500ms cubic-bezier(0.1, -0.6, 0.2, 0)',
			},
		},
	},
	plugins: [require('tailwindcss-touch')()],
	corePlugins: {
		preflight: false,
	},
}
