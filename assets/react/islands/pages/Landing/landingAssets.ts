import carouselArrow from './images/carousel-arrow.png'
import carouselSlide1Visual from './images/carousel-slide-1-visual.png'
import carouselSlide2Visual from './images/carousel-slide-2-visual.png'
import carouselSlide3Visual from './images/carousel-slide-3-visual.png'
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
	carouselSlide1Visual,
	carouselSlide2Visual,
	carouselSlide3Visual,
} as const

export type LandingSlide = {
	step: string
	title: string
	description: string
	stageClass: 'stageSlide1' | 'stageSlide2' | 'stageSlide3'
	visual: string
	visualWidth: number
	visualHeight: number
}

export const landingSlides: LandingSlide[] = [
	{
		step: '01',
		title: 'Add your cargo',
		description:
			'Select item type, fill in dimensions, weight, and quantity. Add pickup and delivery addresses, pick a date.',
		stageClass: 'stageSlide1',
		visual: carouselSlide1Visual,
		visualWidth: 630,
		visualHeight: 356,
	},
	{
		step: '02',
		title: 'Get an instant price',
		description: 'No tenders, no phone calls. The price appears immediately.',
		stageClass: 'stageSlide2',
		visual: carouselSlide2Visual,
		visualWidth: 628,
		visualHeight: 351,
	},
	{
		step: '03',
		title: 'Confirm, pay, and receive',
		description:
			'Pay in one click. A matched, insured carrier picks up your pallet and delivers within 48 hours.',
		stageClass: 'stageSlide3',
		visual: carouselSlide3Visual,
		visualWidth: 613,
		visualHeight: 339,
	},
]
