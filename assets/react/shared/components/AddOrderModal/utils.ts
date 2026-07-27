import { PickupTypeEnum } from '@config/constants'
import { format } from 'date-fns'

import { CargoItemType, PickupTypeT } from './types'

export const formatDate = (d: Date | undefined): string | null =>
	d ? format(d, 'yyyy-MM-dd') : null

export const dimensionsCm = (
	length: number | undefined,
	width: number | undefined,
	height: number | undefined
) => (length && width && height ? `${length}x${width}x${height}` : null)

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

export const formatPickupWindow = (from?: string | null, to?: string | null): string => {
	if (!from && !to) return 'Any time'
	if (from && to) return `${from} – ${to}`
	return from || to || 'Any time'
}

export const whenLabel = (
	pickupType: PickupTypeT,
	pickupTimeFrom?: string | null,
	pickupTimeTo?: string | null,
	pickupDate?: Date
) => {
	const time = formatPickupWindow(pickupTimeFrom, pickupTimeTo)
	const date = format(pickupDate || new Date(), 'dd.MM.yyyy')

	if (pickupType === 'pickup_later') return `${date}, ${time}`
	return `${PickupTypeEnum[pickupType]}, ${time}`
}

/** Normalize API time to an hourly working-day slot "HH:00" (08–18). */
export const normalizePickupTime = (raw?: string | null, fallback = '08:00'): string => {
	if (!raw) return fallback
	const match = raw.trim().match(/^(\d{1,2}):(\d{2})/)
	if (!match) return fallback
	const hour = Math.min(18, Math.max(8, Number(match[1])))
	return `${String(hour).padStart(2, '0')}:00`
}
