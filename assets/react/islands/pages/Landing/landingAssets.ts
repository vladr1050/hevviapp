import carouselArrow from './images/carousel-arrow.png'
import carouselSlide1 from './images/carousel-slide-1.png'
import carouselSlide2 from './images/carousel-slide-2.png'
import carouselSlide3 from './images/carousel-slide-3.png'
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
	carouselSlide1,
	carouselSlide2,
	carouselSlide3,
} as const

export type LandingSlide = {
	step: string
	title: string
	description: string
	image: string
}

export const landingSlides: LandingSlide[] = [
	{
		step: '01',
		title: 'Add your cargo',
		description:
			'Select item type, fill in dimensions, weight, and quantity. Add pickup and delivery addresses, pick a date.',
		image: carouselSlide1,
	},
	{
		step: '02',
		title: 'Get an instant price',
		description: 'No tenders, no phone calls. The price appears immediately.',
		image: carouselSlide2,
	},
	{
		step: '03',
		title: 'Confirm, pay, and receive',
		description:
			'Pay in one click. A matched, insured carrier picks up your pallet and delivers within 48 hours.',
		image: carouselSlide3,
	},
]
