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
