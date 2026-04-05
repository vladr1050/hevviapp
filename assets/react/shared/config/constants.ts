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
	RATE_ORDER = '/rateOrder',
	UPDATE_STATUS = '/updateStatus',
}

export const carrierDeclineRequestUrl = (id: string): string =>
	`/carrier/requests/${id}/decline`

export const carrierConfirmRequestUrl = (id: string): string =>
	`/carrier/requests/${id}/confirm`

export const carrierCancelOrderUrl = (id: string): string =>
	`/carrier/orders/${id}/cancel`

export const userConfirmOrderUrl = (id: string): string =>
	`/user/orders/${id}/confirm`

export const userAbandonOrderUrl = (id: string): string => `/user/orders/${id}/abandon`

export const userCancelOrderUrl = (id: string): string =>
	`/user/orders/${id}/cancel`

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

export enum CargoTypeEnum {
	palette = 1,
	irregular_cargo = 2
}

type CargoType = {
	type_text?: string
	dimensions?: string
	weight?: number
	quantity: number
	name: string
}

export type OrderType = {
	id: string
	// 
	cargo: CargoType[]
	attachments: {filename: string, path: string}[]
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
	subtotal?: string
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


export const MAX_WIDTH = 1000
export const MAX_LENGTH = 1000
export const MAX_HEIGHT = 1000
export const MIN_WEIGHT = 150
// export const MAX_WEIGHT = 700
export const MAX_QUANTITY = 100

export const DEFAULT_LAT = 56.946845
export const DEFAULT_LNG = 24.106075