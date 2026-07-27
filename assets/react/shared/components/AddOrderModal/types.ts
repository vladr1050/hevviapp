import { YearsType } from '@config/constants'

export type CalculateModalType = 'what' | 'where' | 'when' | 'calculate' | undefined

export type CargoItemType = {
	name: string
	width: number
	length: number
	height: number
	weight: number
	quantity: number
}

export type PickupTypeT = 'pickup_ready' | 'pickup_later'

export type FormValues = {
	// WHAT
	cargo: CargoItemType[]
	attachments?: File[]
	keepAttachments?: { filename: string; path: string }[]

	stackable: boolean
	manipulatorNeeded: boolean
	comment: string
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
	/** Working-day window start, e.g. "08:00". */
	pickupTimeFrom: string
	/** Working-day window end, e.g. "18:00". */
	pickupTimeTo: string
	pickupDate?: Date

	// какой этап заполнения формы и на какой можно вернуться
	_step: 1 | 2 | 3
}
