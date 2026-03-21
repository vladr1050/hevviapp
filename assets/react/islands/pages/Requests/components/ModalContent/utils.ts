import { PickupTypeEnum } from '@config/constants'
import { format } from 'date-fns'

import { CargoItemType, PickupTimeT, PickupTypeT } from '../RequestsUser/types'

export const formatDate = (d: Date | undefined): string | null =>
	d ? d.toISOString().split('T')[0] : null

export const formatFileSize = (bytes: number): string => {
	const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
	if (bytes === 0) return 'n/a'

	const i = Math.floor(Math.log2(bytes) / 10)
	const value = bytes / 2 ** (i * 10)

	return `${new Intl.NumberFormat('en', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	}).format(value)} ${sizes[i]}`
}

export const getFileCategory = (file: File): 'pdf' | 'excel' | 'document' | 'unknown' => {
	const mime = file.type.toLowerCase()
	const extension = file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : ''

	if (mime === 'application/pdf' || extension === 'pdf') {
		return 'pdf'
	}

	if (
		[
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel.sheet.macroenabled.12',
			'application/vnd.oasis.opendocument.spreadsheet',
			'text/csv',
		].includes(mime) ||
		['xls', 'xlsx', 'xlsm', 'ods', 'csv'].includes(extension)
	) {
		return 'excel'
	}

	if (
		[
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-word.document.macroenabled.12',
			'application/rtf',
			'application/vnd.oasis.opendocument.text',
			'text/plain',
		].includes(mime) ||
		['doc', 'docx', 'docm', 'rtf', 'odt', 'txt'].includes(extension)
	) {
		return 'document'
	}

	return 'unknown'
}

export const dimensionsCm = (
	width: number | undefined,
	length: number | undefined,
	height: number | undefined
) => (width && length && height ? `${width}x${length}x${height}` : null)

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
