import carouselArrow from './images/carousel-arrow.png'
import carouselSlide1Accent from './images/carousel-slide-1-accent.png'
import carouselSlide1Form from './images/carousel-slide-1.png'
import carouselSlide2 from './images/carousel-slide-2.png'
import carouselSlide3Map from './images/carousel-slide-3-map.png'
import carouselSlide3Phone from './images/carousel-slide-3-phone.png'
import heroFeatures from './images/hero-features.png'
import heroVisual from './images/hero-visual.png'
import landingMobile from './images/landing-mobile.png'
import logoFooter from './images/logo-footer.png'
import logo from './images/logo.png'
import registrationTrucks from './images/registration-trucks.png'

export const landingAssets = {
	logo,
	logoFooter,
	heroVisual,
	heroFeatures,
	registrationTrucks,
	landingMobile,
	carouselArrow,
	carouselSlide1Form,
	carouselSlide1Accent,
	carouselSlide2,
	carouselSlide3Map,
	carouselSlide3Phone,
} as const

export type LandingSlideLayerClass =
	| 'slide1Accent'
	| 'slide1Form'
	| 'slideMain'
	| 'slide3Map'
	| 'slide3Phone'

export type LandingSlideLayer = {
	image: string
	className: LandingSlideLayerClass
}

export type LandingSlide = {
	step: string
	title: string
	description: string
	accentClass?: 'accentSlide2' | 'accentSlide3'
	stageClass?: 'stageSlide1'
	layers: LandingSlideLayer[]
}

export const landingSlides: LandingSlide[] = [
	{
		step: '01',
		title: 'Add your cargo',
		description:
			'Select item type, fill in dimensions, weight, and quantity. Add pickup and delivery addresses, pick a date.',
		stageClass: 'stageSlide1',
		layers: [
			{ image: carouselSlide1Accent, className: 'slide1Accent' },
			{ image: carouselSlide1Form, className: 'slide1Form' },
		],
	},
	{
		step: '02',
		title: 'Get an instant price',
		description: 'No tenders, no phone calls. The price appears immediately.',
		accentClass: 'accentSlide2',
		layers: [{ image: carouselSlide2, className: 'slideMain' }],
	},
	{
		step: '03',
		title: 'Confirm, pay, and receive',
		description:
			'Pay in one click. A matched, insured carrier picks up your pallet and delivers within 48 hours.',
		accentClass: 'accentSlide3',
		layers: [
			{ image: carouselSlide3Map, className: 'slide3Map' },
			{ image: carouselSlide3Phone, className: 'slide3Phone' },
		],
	},
]
