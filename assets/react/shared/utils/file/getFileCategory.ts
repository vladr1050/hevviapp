export const getFileCategory = (file: File | string): 'pdf' | 'excel' | 'document' | 'unknown' => {
	if (typeof file === 'string') {
		const extension = file.includes('.') ? file.split('.').pop()!.toLowerCase() : ''

		if (extension === 'pdf') {
			return 'pdf'
		}

		if (['xls', 'xlsx', 'xlsm', 'ods', 'csv'].includes(extension)) {
			return 'excel'
		}
		if (['doc', 'docx', 'docm', 'rtf', 'odt', 'txt'].includes(extension)) {
			return 'document'
		}

		return 'unknown'
	}

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
