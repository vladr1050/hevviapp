import carouselArrow from './images/carousel-arrow.png'
import carouselSlide1 from './images/carousel-slide-1.png'
import carouselSlide2 from './images/carousel-slide-2.png'
import carouselSlide3 from './images/carousel-slide-3.png'
import decoLine1 from './images/deco-line-1.png'
import decoLine2 from './images/deco-line-2.png'
import decoLine3 from './images/deco-line-3.png'
import heroComposite from './images/hero-composite.png'
import landingMobile from './images/landing-mobile.png'
import loginCluster from './images/login-cluster.png'
import logo from './images/logo.png'

export const landingAssets = {
	logo,
	loginCluster,
	heroComposite,
	landingMobile,
	decoLine1,
	decoLine2,
	decoLine3,
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
		description: 'No tenders, no phone calls.\nThe price appears immediately.',
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
