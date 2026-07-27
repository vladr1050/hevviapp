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
	_pickupTimeFrom?: string | null,
	_pickupTimeTo?: string | null,
	pickupDate?: Date
) => {
	// Time window stays only in the When-step dropdowns (defaults 09:00–17:00),
	// not in the tab / request-form summary label.
	if (pickupType === 'pickup_later') {
		return format(pickupDate || new Date(), 'dd.MM.yyyy')
	}

	return PickupTypeEnum[pickupType]
}

/** Normalize API time to an hourly slot "HH:00" (00–24). End-of-day 23:59 → 24:00. */
export const normalizePickupTime = (raw?: string | null, fallback = '09:00'): string => {
	if (!raw) return fallback
	const trimmed = raw.trim()
	if (trimmed === '23:59' || trimmed.startsWith('23:59')) return '24:00'
	const match = trimmed.match(/^(\d{1,2}):(\d{2})/)
	if (!match) return fallback
	const hour = Number(match[1])
	if (hour === 24) return '24:00'
	if (Number.isNaN(hour) || hour < 0 || hour > 23) return fallback
	return `${String(hour).padStart(2, '0')}:00`
}
