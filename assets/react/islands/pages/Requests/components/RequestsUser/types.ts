import { YearsType } from '@config/constants'

export type CalculateModalType = 'what' | 'where' | 'when' | 'calculate' | undefined

export type CargoType = 'palette' | 'irregular_cargo'

export type CargoItemType = {
	type: CargoType
	width: number
	length: number
	height: number
	weight: number
	quantity: number
}

export type PickupTypeT = 'pickup_ready' | 'pickup_later'
export type PickupTimeT = 'anytime' | '8:00-13:00' | '13:00-18:00'

export type FormValues = {
	// WHAT
	cargo: CargoItemType[]
	stackable: boolean
	manipulatorNeeded: boolean
	documents?: File[]
	comments: string
	// WHERE
	from: string
	to: string
	pickupLatitude: number | undefined
	pickupLongitude: number | undefined
	dropoutLatitude: number | undefined
	dropoutLongitude: number | undefined

	// WHEN
	pickupType: PickupTypeT
	pickupMonth: number
	pickupYear: YearsType
	pickupTime: PickupTimeT
	pickupDate?: Date
}

export const MAX_WIDTH = 1000
export const MAX_LENGTH = 1000
export const MAX_HEIGHT = 1000
export const MIN_WEIGHT = 150
export const MAX_WEIGHT = 700
export const MAX_QUANTITY = 100
