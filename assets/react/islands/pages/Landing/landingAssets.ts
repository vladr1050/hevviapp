import carouselArrow from './images/carousel-arrow.png'
import carouselSlide1Accent from './images/carousel-slide-1-accent.png'
import carouselSlide1Cursor from './images/carousel-slide-1-cursor.png'
import carouselSlide1Visual from './images/carousel-slide-1-visual.png'
import carouselSlide2Accent from './images/carousel-slide-2-accent.png'
import carouselSlide2Cursor from './images/carousel-slide-2-cursor.png'
import carouselSlide2Visual from './images/carousel-slide-2-visual.png'
import carouselSlide3Visual from './images/carousel-slide-3-visual.png'
import heroCargo from './images/hero-cargo.png'
import heroDeliveryPanel from './images/hero-delivery-panel.png'
import heroFeatures from './images/hero-features.png'
import heroMapContent from './images/hero-map-content.png'
import heroMapGradient from './images/hero-map-gradient.png'
import heroVisual from './images/hero-visual.png'
import landingMobile from './images/landing-mobile.png'
import loginIcon from './images/login-icon.png'
import logoFooter from './images/logo-footer.png'
import logo from './images/logo.png'
import registrationTrucks from './images/registration-trucks.png'

export const landingAssets = {
	logo,
	logoFooter,
	loginIcon,
	heroVisual,
	heroMapContent,
	heroMapGradient,
	heroCargo,
	heroDeliveryPanel,
	heroFeatures,
	registrationTrucks,
	landingMobile,
	carouselArrow,
	carouselSlide1Visual,
	carouselSlide2Visual,
	carouselSlide3Visual,
	carouselSlide1Accent,
	carouselSlide2Accent,
	carouselSlide1Cursor,
	carouselSlide2Cursor,
} as const

export type SlideVisualLayer = {
	className:
		| 'visualAccent'
		| 'visualMain'
		| 'visualCursor'
	src: string
	width: number
	height: number
}

export type LandingSlide = {
	step: string
	title: string
	description: string
	stageClass: 'stageSlide1' | 'stageSlide2' | 'stageSlide3'
	stageWidth: number
	stageHeight: number
	layers: SlideVisualLayer[]
}

export const landingSlides: LandingSlide[] = [
	{
		step: '01',
		title: 'Add your cargo',
		description:
			'Select item type, fill in dimensions, weight, and quantity. Add pickup and delivery addresses, pick a date.',
		stageClass: 'stageSlide1',
		stageWidth: 687.5,
		stageHeight: 409,
		layers: [
			{
				className: 'visualAccent',
				src: carouselSlide1Accent,
				width: 507,
				height: 290,
			},
			{
				className: 'visualMain',
				src: carouselSlide1Visual,
				width: 661.5,
				height: 380.4,
			},
			{
				className: 'visualCursor',
				src: carouselSlide1Cursor,
				width: 19.5,
				height: 31.2,
			},
		],
	},
	{
		step: '02',
		title: 'Get an instant price',
		description: 'No tenders, no phone calls. The price appears immediately.',
		stageClass: 'stageSlide2',
		stageWidth: 685,
		stageHeight: 405,
		layers: [
			{
				className: 'visualAccent',
				src: carouselSlide2Accent,
				width: 507,
				height: 290,
			},
			{
				className: 'visualMain',
				src: carouselSlide2Visual,
				width: 659,
				height: 380,
			},
			{
				className: 'visualCursor',
				src: carouselSlide2Cursor,
				width: 19.5,
				height: 31.2,
			},
		],
	},
	{
		step: '03',
		title: 'Confirm, pay, and receive',
		description:
			'Pay in one click. A matched, insured carrier picks up your pallet and delivers within 48 hours.',
		stageClass: 'stageSlide3',
		stageWidth: 613,
		stageHeight: 339,
		layers: [
			{
				className: 'visualMain',
				src: carouselSlide3Visual,
				width: 613,
				height: 339,
			},
		],
	},
]
