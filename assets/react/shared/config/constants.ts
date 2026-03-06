export const EMAIL = 'support@hevvi.app'
export const PHONE = '+37122334455'

export const EMPTY_STRING = '—'


export enum Routes {
	HOME = '/',
	REQUESTS = '/user/requests',
	ORDERS = '/user/orders',
	PROFILE = '/user/profile',
	LOGIN = '/login',
	LOGOUT = '/logout',
	REGISTRATION = '/registration',
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

export type OrderType = {
	id: string
	status: OrderStatusEnum
	status_text: string
	price?: string
	address: {
		from?: string
		to?: string
	}
	name?: string
	item?: number
	cargoDimensions?: string
	cargoWeight?: number
	comment?: string
	pickup_date?: string
	carrier?: string
	pickup_latitude?: string
	pickup_longitude?: string
	dropout_latitude?: string
	dropout_longitude?: string
	stackable?: boolean
	manipulator_needed?: boolean
	pickup_time_from?: string
	pickup_time_to?: string
	delivery_time_from?: string
	delivery_time_to?: string
	pickup_request_date?: string
	delivery_date?: string
	type?: string
	vat?: string
	brutto?: string
	fee?: string
}

export type AccountType = 'Sender' | 'Carrier'
