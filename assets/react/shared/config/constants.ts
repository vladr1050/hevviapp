export const EMAIL = 'support@hevvi.app'
export const PHONE = '+37122334455'

export const EMPTY_STRING = '—'


export enum Routes {
	HOME = '/',
	LOGIN = '/login',
	LOGOUT = '/logout',
	REGISTRATION = '/registration',
	// USER
	USER_REQUESTS = '/user/requests',
	USER_ORDERS = '/user/orders',
	USER_PROFILE = '/user/profile',
	// CARRIER
	CARRIER_REQUESTS = '/carrier/requests',
	CARRIER_ORDERS = '/carrier/orders',
	CARRIER_PROFILE = '/carrier/profile',

}

export enum FormActions {
	LOGIN = '/login',
	REGISTRATION = '/registration',
	CALCULATE = '/calculate',
	CONFIRM_ORDER = '/user/confirmOrder',
	CANCEL_ORDER = '/cancelOrder',
	RATE_ORDER = '/rateOrder',
	UPDATE_STATUS = '/updateStatus',
}

export const carrierDeclineRequestUrl = (id: string): string =>
	`/carrier/requests/${id}/decline`

export const carrierConfirmRequestUrl = (id: string): string =>
	`/carrier/requests/${id}/confirm`

export const carrierCancelOrderUrl = (id: string): string =>
	`/carrier/orders/${id}/cancel`

export const carrierUpdateStatusUrl = (id: string): string =>
	`/carrier/orders/${id}/update-status`

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

export enum PickupTypeEnum {
	pickup_ready = 'Pickup ready',
	pickup_later = 'Pickup later'
}

export enum OrderStatusEnum {
	'DRAFT' = 1,
	'OFFERED' = 2,
	'ACCEPTED' = 3,
	'INVOICED' = 4,
	'PAID' = 5,
	'ASSIGNED' = 6,
	'AWAITING_PICKUP' = 7,
	'PICKUP_DONE' = 8,
	'IN_TRANSIT' = 9,
	'DELIVERED' = 10,
	'CANCELLED' = -1
}

// FIXME PAVEL
type CargoType = {
	type: 'palette' | 'irregular_cargo'
	dimensions?: string
	weight?: number
	quantity: number
}

export type OrderType = {
	id: string
	// 
	cargo: CargoType[]
	stackable?: boolean
	manipulator_needed?: boolean
	comment?: string
	address: {
		from?: string
		to?: string
	}
	// pickup
	pickup_date?: string
	pickup_latitude?: string
	pickup_longitude?: string
	pickup_time_from?: string
	pickup_time_to?: string
	pickup_request_date?: string

	// delivery
	dropout_latitude?: string
	dropout_longitude?: string
	delivery_time_from?: string
	delivery_time_to?: string
	delivery_date?: string

	status: OrderStatusEnum
	status_text: string
	price?: string
	carrier?: string
	paid_date?: string
	delivered_date?: string
	type?: string
	vat?: string
	brutto?: string
	fee?: string
	sender?: { 
		first_name?: string
		last_name?: string
	}
}

export type ShortOrderType = {
	address: { from: string; to: string }
	comment?: string
	id: string
	item: number
	type: string
}

export type AccountType = 'Sender' | 'Carrier'
