import { PickupTypeEnum } from '@config/constants'
import { format } from 'date-fns'

import { CargoItemType, PickupTimeT, PickupTypeT } from '../RequestsUser/types'

export const formatDate = (d: Date | undefined): string | null =>
	d ? d.toISOString().split('T')[0] : null

export const dimensionsCm = (
	width: number | undefined,
	length: number | undefined,
	height: number | undefined
) => (width && length && height ? `${width}x${length}x${height}` : null)

export const cargoTypeMap: Record<string, 1 | 2> = {
	palette: 1,
	irregular_cargo: 2,
}

export const whatLabel = (cargos: CargoItemType[]) => {
	const [palettes, irregular] = cargos.reduce(
		(acc, curr) => [
			acc[0] + (curr.type === 'palette' ? 1 : 0),
			acc[1] + (curr.type === 'irregular_cargo' ? 1 : 0),
		],
		[0, 0]
	)

	const palettesLabel = `${palettes} palette${palettes > 1 ? 's' : ''}`
	const irregularLabel = `${irregular} irregular cargo`

	if (!!palettes && !!irregular) return `${palettesLabel}, ${irregularLabel}`

	if (palettes) return palettesLabel
	if (irregular) return irregularLabel

	return undefined
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
