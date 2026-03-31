export const downloadFile = (file: File) => {
	const url = URL.createObjectURL(file)
	const a = document.createElement('a')

	a.href = url
	a.download = file.name
	document.body.appendChild(a)
	a.click()
	a.remove()

	URL.revokeObjectURL(url)
}

export const downloadFileByUrl = (url: string, filename: string) => {
	const a = document.createElement('a')

	a.href = url
	a.download = filename
	document.body.appendChild(a)
	a.click()
	a.remove()
}
