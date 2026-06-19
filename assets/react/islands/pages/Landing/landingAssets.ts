import carouselArrow from './images/carousel-arrow.png'
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
		visual: '',
		visualWidth: 687.5,
		visualHeight: 409.02,
	},
	{
		step: '02',
		title: 'Get an instant price',
		description: 'No tenders, no phone calls. The price appears immediately.',
		stageClass: 'stageSlide2',
		visual: '',
		visualWidth: 685,
		visualHeight: 404.98,
	},
	{
		step: '03',
		title: 'Confirm, pay, and receive',
		description:
			'Pay in one click. A matched, insured carrier picks up your pallet and delivers within 48 hours.',
		stageClass: 'stageSlide3',
		visual: '',
		visualWidth: 689,
		visualHeight: 407,
	},
]
