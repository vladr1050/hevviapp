export const getDefaultMapData = ({
	from,
	to,
}: {
	from: { lat?: string; lng?: string }
	to: { lat?: string; lng?: string }
}) => {
	const fromLat = typeof from.lat === 'string' && !isNaN(Number(from.lat)) ? Number(from.lat) : null
	const fromLng = typeof from.lng === 'string' && !isNaN(Number(from.lng)) ? Number(from.lng) : null
	const toLat = typeof to.lat === 'string' && !isNaN(Number(to.lat)) ? Number(to.lat) : null
	const toLng = typeof to.lng === 'string' && !isNaN(Number(to.lng)) ? Number(to.lng) : null

	let defaultPosition = undefined
	let defaultBounds = undefined

	if (fromLat === null || fromLng === null || toLat === null || toLng === null) {
		if (fromLat !== null && fromLng !== null) {
			defaultPosition = [fromLat, fromLng]
			defaultBounds = [[fromLat, fromLng]]
		} else if (toLat !== null && toLng !== null) {
			defaultPosition = [toLat, toLng]
			defaultBounds = [[toLat, toLng]]
		}
	} else {
		defaultPosition = [(fromLat + toLat) / 2, (fromLng + toLng) / 2]
		defaultBounds = [
			[fromLat, fromLng],
			[toLat, toLng],
		]
	}

	return { defaultPosition, defaultBounds }
}
