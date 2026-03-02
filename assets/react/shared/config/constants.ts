export const EMAIL = 'support@hevvi.app'
export const PHONE = '+37122334455'


export enum Routes {
	HOME = '/admin',
	DASHBOARD = '/admin/dashboard',
	REQUESTS = '/admin/requests',
	ORDERS = '/admin/orders',
	PROFILE = '/admin/profile',
	LOGIN = '/admin/login',
	REGISTRATION = '/admin/registration',
} 

export enum FormActions {
	LOGIN = '/login',
	REGISTRATION = '/registration',
	CALCULATE = '/calculate',
	CONFIRM_ORDER = '/confirmOrder',
	CANCEL_ORDER = '/cancelOrder',
	RATE_ORDER = '/rateOrder',
} 

export const months = [
	'january',
	'february',
	'march',
	'april',
	'may',
	'june',
	'july',
	'august',
	'september',
	'october',
	'november',
	'december',
] as const

export type MonthsType = typeof months[number]


export const years = [
	'2026',
	'2027',
] as const

export type YearsType = typeof years[number]


export enum OrderStatusId {
	awaitingConfirmation = 0,
	awaitingPayment = 1,
	carrierMatched = 2,
	awaitingPickup = 3,
	inTransit = 4,
	delivery = 5,
	delivered = 6,
}


export type OrderType = {
	id: string
	name: string
	type: string
	size: string
	weight: number
	additionals: { stackability: boolean; lift: boolean }
	routes: {
		from: {
			address: string
			loadingReady: string
			loadingWindow: string
			deliveryDate: string
			position: { lat: number; lng: number }
		}
		to: {
			address: string
			loadingReady: string
			deliveryWindow: string
			deliveryDate: string
			position: { lat: number; lng: number }
		}
		polyline: [number, number][]
	}
	comments: string
	//
	price: string
	vat: string
	total: string
	platform: string

	deliveryTime?: string

	sender?: {
		image: string
		name: string
		company: string
	}

	status:
		| 'awaitingConfirmation'
		| 'awaitingPayment'
		| 'carrierMatched'
		| 'awaitingPickup'
		| 'inTransit'
		| 'delivery'
		| 'delivered'
}

