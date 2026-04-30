/** Fallback if /api/public/support-contact is empty or fails */
export const EMAIL = 'support@hevvi.app'
export const PHONE = '+37122334455'

export const PUBLIC_SUPPORT_CONTACT_URL = '/api/public/support-contact'

/** Current published Terms & Conditions HTML for the logged-in portal user (JWT). */
export const TERMS_CURRENT_URL = '/api/terms/current'

/** Published terms by audience for login / registration (no auth). ?audience=sender|carrier */
export const TERMS_PUBLIC_CURRENT_URL = '/api/public/terms/current'

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
	'APPROVED' = 11,
	'CANCELLED' = -1,
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
	/** Business order no., e.g. HEV-00042 (from DB order_number). */
	reference?: string
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
	/** Sender order page: total = (freight + freight VAT) + (platform fee + operator VAT on fee). */
	sender_total?: string
	/** Carrier freight VAT on base only (formatted); total = base + this VAT. */
	carrier_freight_vat?: string
	carrier_freight_total?: string
	/** e.g. "21%" — later from executing carrier profile. */
	carrier_freight_vat_rate_display?: string
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

/** Public JSON for map defaults and geographic restrictions (Sonata-configurable). */
export const PUBLIC_MAP_SETTINGS_URL = '/api/public/map-settings'

/** Google geocoding proxy (enabled when `googleAddressSearch` in map settings). */
export const PUBLIC_GEOCODE_AUTOCOMPLETE_URL = '/api/public/geocode/autocomplete'
export const PUBLIC_GEOCODE_PLACE_URL = '/api/public/geocode/place'
export const PUBLIC_GEOCODE_REVERSE_URL = '/api/public/geocode/reverse'