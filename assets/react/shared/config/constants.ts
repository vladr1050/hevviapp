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


