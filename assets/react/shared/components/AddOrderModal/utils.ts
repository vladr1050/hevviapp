import { PickupTypeEnum } from '@config/constants'
import { format } from 'date-fns'

import { CargoItemType, PickupTimeT, PickupTypeT } from './types'

// import { CargoItemType, PickupTimeT, PickupTypeT } from '../RequestsUser/types'

export const formatDate = (d: Date | undefined): string | null =>
	d ? format(d, 'yyyy-MM-dd') : null

export const dimensionsCm = (
	width: number | undefined,
	length: number | undefined,
	height: number | undefined
) => (width && length && height ? `${width}x${length}x${height}` : null)

export const whatLabel = (cargos: CargoItemType[]) => {
	if (!cargos.length) return undefined

	return `${cargos.length} item${cargos.length > 1 ? 's' : ''}`
}

export const whereLabel = (
	from: {
		label: string
		lat?: number
	},
	to: {
		label: string
		lat?: number
	}
) => {
	if (!from.lat?.toString().length || !to.lat?.toString().length) return undefined

	return `${from.label} → ${to.label}`
}

export const whenLabel = (pickupType: PickupTypeT, pickupTime: PickupTimeT, pickupDate?: Date) => {
	const time = pickupTime === 'anytime' ? 'Any time' : pickupTime
	const date = format(pickupDate || new Date(), 'dd.MM.yyyy')

	if (pickupType === 'pickup_later') return `${date}, ${time}`
	else return `${PickupTypeEnum[pickupType]}, ${time}`

	return undefined
}
