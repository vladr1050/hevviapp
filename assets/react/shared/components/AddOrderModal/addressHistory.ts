const ADDRESS_KEY = 'hevvi-where-address-searches'
const ROUTE_KEY = 'hevvi-where-route-searches'
export const WHERE_HISTORY_LIMIT = 5

export type SavedAddress = {
	label: string
	lat: number
	lng: number
}

export type SavedRoute = {
	from: SavedAddress
	to: SavedAddress
}

const canUseStorage = (): boolean => typeof window !== 'undefined' && !!window.localStorage

const readJson = <T>(key: string): T[] => {
	if (!canUseStorage()) return []
	try {
		const raw = localStorage.getItem(key)
		if (!raw) return []
		const parsed = JSON.parse(raw) as unknown
		return Array.isArray(parsed) ? (parsed as T[]) : []
	} catch {
		return []
	}
}

const writeJson = (key: string, value: unknown): void => {
	if (!canUseStorage()) return
	try {
		localStorage.setItem(key, JSON.stringify(value))
	} catch {
		/* quota / private mode */
	}
}

const isFiniteCoord = (n: unknown): n is number => typeof n === 'number' && Number.isFinite(n)

const normalizeAddress = (item: Partial<SavedAddress> | null | undefined): SavedAddress | null => {
	if (!item || typeof item.label !== 'string') return null
	const label = item.label.trim()
	if (!label || !isFiniteCoord(item.lat) || !isFiniteCoord(item.lng)) return null
	return { label, lat: item.lat, lng: item.lng }
}

const normalizeRoute = (item: Partial<SavedRoute> | null | undefined): SavedRoute | null => {
	const from = normalizeAddress(item?.from)
	const to = normalizeAddress(item?.to)
	if (!from || !to) return null
	return { from, to }
}

const addressKey = (a: SavedAddress): string =>
	`${a.label.toLowerCase()}|${a.lat.toFixed(5)}|${a.lng.toFixed(5)}`

const routeKey = (r: SavedRoute): string => `${addressKey(r.from)}=>${addressKey(r.to)}`

export const getAddressHistory = (): SavedAddress[] =>
	readJson<Partial<SavedAddress>>(ADDRESS_KEY)
		.map(normalizeAddress)
		.filter((v): v is SavedAddress => v !== null)
		.slice(0, WHERE_HISTORY_LIMIT)

export const pushAddressHistory = (address: SavedAddress): SavedAddress[] => {
	const next = normalizeAddress(address)
	if (!next) return getAddressHistory()
	const rest = getAddressHistory().filter((a) => addressKey(a) !== addressKey(next))
	const list = [next, ...rest].slice(0, WHERE_HISTORY_LIMIT)
	writeJson(ADDRESS_KEY, list)
	return list
}

export const getRouteHistory = (): SavedRoute[] =>
	readJson<Partial<SavedRoute>>(ROUTE_KEY)
		.map(normalizeRoute)
		.filter((v): v is SavedRoute => v !== null)
		.slice(0, WHERE_HISTORY_LIMIT)

export const pushRouteHistory = (route: SavedRoute): SavedRoute[] => {
	const next = normalizeRoute(route)
	if (!next) return getRouteHistory()
	const rest = getRouteHistory().filter((r) => routeKey(r) !== routeKey(next))
	const list = [next, ...rest].slice(0, WHERE_HISTORY_LIMIT)
	writeJson(ROUTE_KEY, list)
	return list
}

export const formatRouteLabel = (route: SavedRoute): string =>
	`${route.from.label} → ${route.to.label}`
