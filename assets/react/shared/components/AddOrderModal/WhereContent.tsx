import { ChangeEvent, type FC, Suspense, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import {
	Control,
	Controller,
	UseFormRegister,
	UseFormSetValue,
	UseFormWatch,
} from 'react-hook-form'
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet'

import {
	DEFAULT_LAT,
	DEFAULT_LNG,
	PUBLIC_GEOCODE_AUTOCOMPLETE_URL,
	PUBLIC_GEOCODE_PLACE_URL,
	PUBLIC_GEOCODE_REVERSE_URL,
	PUBLIC_MAP_SETTINGS_URL,
	ShortOrderType,
} from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'
// @ts-ignore
import L from 'leaflet'

// @ts-ignore
import CustomIcon from '../OrderCard/CustomMarker.svg'

import styles from './ModalContent.module.css'

import {
	getAddressHistory,
	getRouteHistory,
	pushAddressHistory,
	pushRouteHistory,
	SavedAddress,
	WHERE_HISTORY_LIMIT,
} from './addressHistory'
import { FormValues } from './types'

export type MapPickTarget = 'from' | 'to'

export interface PublicMapBoundingBox {
	minLatitude: number
	maxLatitude: number
	minLongitude: number
	maxLongitude: number
}

export interface PublicMapSettings {
	restrictGeographicSearch: boolean
	nominatimCountryCodes: string | null
	boundingBox: PublicMapBoundingBox | null
	map: {
		center: { latitude: number; longitude: number }
		zoom: number
		maxBounds: [[number, number], [number, number]] | null
	}
	nominatimApiUrl: string
	/** When true, address search and reverse geocode use the Symfony Google proxy (see geocode URLs in constants). */
	googleAddressSearch?: boolean
}

interface WhereContentProps {
	watch: UseFormWatch<FormValues>
	control: Control<FormValues, any, FormValues>
	setValue: UseFormSetValue<FormValues>
	register: UseFormRegister<FormValues>
	recentOrders?: ShortOrderType[]
	defaultPosition?: {
		from: {
			lat: number
			lng: number
		} | null
		to: {
			lat: number
			lng: number
		} | null
	}
}

const parseCoord = (value?: string | null): number | null => {
	if (value == null || value === '') return null
	const n = Number(value)
	return Number.isFinite(n) ? n : null
}

type RecentRouteItem = {
	from: { label: string; lat: number | null; lng: number | null }
	to: { label: string; lat: number | null; lng: number | null }
}

const routeKeyOf = (route: RecentRouteItem): string =>
	`${route.from.label.trim().toLowerCase()}=>${route.to.label.trim().toLowerCase()}`

const routeFromOrder = (order: ShortOrderType): RecentRouteItem | null => {
	const fromLabel = order.address?.from?.trim()
	const toLabel = order.address?.to?.trim()
	if (!fromLabel || !toLabel) return null
	return {
		from: {
			label: fromLabel,
			lat: parseCoord(order.pickup_latitude),
			lng: parseCoord(order.pickup_longitude),
		},
		to: {
			label: toLabel,
			lat: parseCoord(order.dropout_latitude),
			lng: parseCoord(order.dropout_longitude),
		},
	}
}

const mergeRecentRoutes = (orders: ShortOrderType[] | undefined): RecentRouteItem[] => {
	const seen = new Set<string>()
	const out: RecentRouteItem[] = []
	const push = (route: RecentRouteItem) => {
		const key = routeKeyOf(route)
		if (!key || seen.has(key)) return
		seen.add(key)
		out.push(route)
	}
	for (const route of getRouteHistory()) {
		push({
			from: { label: route.from.label, lat: route.from.lat, lng: route.from.lng },
			to: { label: route.to.label, lat: route.to.lat, lng: route.to.lng },
		})
	}
	for (const order of orders ?? []) {
		const route = routeFromOrder(order)
		if (route) push(route)
	}
	return out.slice(0, WHERE_HISTORY_LIMIT)
}

const formatRecentRouteLabel = (route: RecentRouteItem): string =>
	`${route.from.label} → ${route.to.label}`

const NOMINATIM_BROWSER_USER_AGENT = 'HeviiTransportApp/1.0'

const defaultMapSettings = (): PublicMapSettings => ({
	restrictGeographicSearch: false,
	nominatimCountryCodes: null,
	boundingBox: null,
	map: {
		center: { latitude: DEFAULT_LAT, longitude: DEFAULT_LNG },
		zoom: 10,
		maxBounds: null,
	},
	nominatimApiUrl: 'https://nominatim.openstreetmap.org',
	googleAddressSearch: false,
})

async function fetchPublicMapSettings(): Promise<PublicMapSettings> {
	try {
		const res = await fetch(PUBLIC_MAP_SETTINGS_URL, { credentials: 'same-origin' })
		if (!res.ok) {
			return defaultMapSettings()
		}
		const data = (await res.json()) as PublicMapSettings
		if (!data?.map?.center) {
			return defaultMapSettings()
		}
		return data
	} catch {
		return defaultMapSettings()
	}
}

function parseCountryCodes(raw: string | null): string[] {
	if (!raw?.trim()) {
		return []
	}
	return raw
		.toLowerCase()
		.split(/[,\s;]+/)
		.map((s) => s.trim())
		.filter(Boolean)
}

function validateBbox(settings: PublicMapSettings, lat: number, lng: number): string | null {
	if (!settings.restrictGeographicSearch) {
		return null
	}
	const bb = settings.boundingBox
	if (!bb) {
		return null
	}
	if (
		lat < bb.minLatitude ||
		lat > bb.maxLatitude ||
		lng < bb.minLongitude ||
		lng > bb.maxLongitude
	) {
		return 'This location is outside the allowed map area.'
	}
	return null
}

function validateCountryCodes(
	settings: PublicMapSettings,
	address?: { country_code?: string } | null
): string | null {
	if (!settings.restrictGeographicSearch) {
		return null
	}
	const allowed = parseCountryCodes(settings.nominatimCountryCodes)
	if (allowed.length === 0) {
		return null
	}
	const cc = address?.country_code?.toLowerCase() ?? ''
	if (!cc || !allowed.includes(cc)) {
		return 'This location is outside the allowed countries.'
	}
	return null
}

interface GeocodePrediction {
	description: string
	placeId: string
}

interface GeocodeResolved {
	displayLine: string
	latitude: number
	longitude: number
	countryCode: string | null
}

type AddressSuggestion =
	| { source: 'nominatim'; data: NominatimResult }
	| { source: 'google'; data: GeocodePrediction }
	| { source: 'history'; data: SavedAddress }

function newGeocodeSessionToken(): string {
	try {
		if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
			return crypto.randomUUID()
		}
	} catch {
		/* ignore */
	}
	return `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`
}

interface NominatimResult {
	place_id: number
	display_name: string
	lat: string
	lon: string
	address?: {
		road?: string
		street?: string
		house_number?: string
		postcode?: string
		city?: string
		town?: string
		village?: string
		municipality?: string
		country?: string
		country_code?: string
	}
}

const formatNominatimAddress = (result: NominatimResult): string => {
	const addr = result.address || {}
	const parts: string[] = []

	const street = addr.road || addr.street || ''
	const houseNumber = addr.house_number || ''
	if (street) {
		parts.push(houseNumber ? `${street} ${houseNumber}` : street)
	}

	const postcode = addr.postcode || ''
	const city = addr.city || addr.town || addr.village || addr.municipality || ''
	if (postcode && city) {
		parts.push(`${postcode} ${city}`)
	} else if (city) {
		parts.push(city)
	} else if (postcode) {
		parts.push(postcode)
	}

	if (addr.country) {
		parts.push(addr.country)
	}

	return parts.length > 0 ? parts.join(', ') : result.display_name
}

async function resolveAddressLabel(
	settings: PublicMapSettings,
	label: string,
): Promise<SavedAddress | null> {
	const trimmed = label.trim()
	if (!trimmed) return null
	try {
		if (settings.googleAddressSearch === true) {
			const autoRes = await fetch(PUBLIC_GEOCODE_AUTOCOMPLETE_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({
					input: trimmed,
					sessionToken: newGeocodeSessionToken(),
				}),
			})
			if (!autoRes.ok) return null
			const json = (await autoRes.json()) as { predictions?: GeocodePrediction[] }
			const first = json.predictions?.[0]
			if (!first) return null
			const placeRes = await fetch(PUBLIC_GEOCODE_PLACE_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({
					placeId: first.placeId,
					sessionToken: newGeocodeSessionToken(),
				}),
			})
			if (!placeRes.ok) return null
			const dto = (await placeRes.json()) as GeocodeResolved
			return { label: dto.displayLine || trimmed, lat: dto.latitude, lng: dto.longitude }
		}

		const root = settings.nominatimApiUrl.replace(/\/$/, '')
		const params = new URLSearchParams({
			q: trimmed,
			format: 'json',
			addressdetails: '1',
			limit: '1',
			'accept-language': 'en',
		})
		if (settings.nominatimCountryCodes?.trim()) {
			params.set(
				'countrycodes',
				settings.nominatimCountryCodes.replace(/\s+/g, '').toLowerCase(),
			)
		}
		const res = await fetch(`${root}/search?${params}`, {
			headers: { 'User-Agent': NOMINATIM_BROWSER_USER_AGENT },
		})
		const data = (await res.json()) as NominatimResult[]
		const first = Array.isArray(data) ? data[0] : null
		if (!first) return null
		return {
			label: formatNominatimAddress(first) || trimmed,
			lat: parseFloat(first.lat),
			lng: parseFloat(first.lon),
		}
	} catch {
		return null
	}
}

async function reverseGeocodeGoogle(lat: number, lng: number): Promise<GeocodeResolved | null> {
	const params = new URLSearchParams({ lat: String(lat), lng: String(lng) })
	try {
		const res = await fetch(`${PUBLIC_GEOCODE_REVERSE_URL}?${params}`, { credentials: 'same-origin' })
		if (!res.ok) {
			return null
		}
		const data = (await res.json()) as Partial<GeocodeResolved>
		if (
			typeof data.displayLine !== 'string' ||
			typeof data.latitude !== 'number' ||
			typeof data.longitude !== 'number'
		) {
			return null
		}
		return data as GeocodeResolved
	} catch {
		return null
	}
}

async function reverseGeocode(
	baseUrl: string,
	lat: number,
	lng: number
): Promise<NominatimResult | null> {
	const root = baseUrl.replace(/\/$/, '')
	const params = new URLSearchParams({
		lat: String(lat),
		lon: String(lng),
		format: 'json',
		addressdetails: '1',
	})
	try {
		const res = await fetch(`${root}/reverse?${params}`, {
			headers: { 'User-Agent': NOMINATIM_BROWSER_USER_AGENT },
		})
		if (!res.ok) {
			return null
		}
		return (await res.json()) as NominatimResult
	} catch {
		return null
	}
}

export const WhereContent: FC<WhereContentProps> = ({
	watch,
	control,
	setValue,
	register,
	defaultPosition,
	recentOrders,
}) => {
	const [mapSettings, setMapSettings] = useState<PublicMapSettings | null>(null)
	const [fromMarkerPos, setFromMarkerPos] = useState<{ lat: number; lng: number } | null>(
		defaultPosition?.from || null
	)
	const [toMarkerPos, setToMarkerPos] = useState<{ lat: number; lng: number } | null>(
		defaultPosition?.to || null
	)
	/** Which address field last had focus — map clicks apply to this field. */
	const mapClickTargetRef = useRef<MapPickTarget>('from')
	const [geoHint, setGeoHint] = useState<string | null>(null)
	const [recentRoutes, setRecentRoutes] = useState<RecentRouteItem[]>(() =>
		mergeRecentRoutes(recentOrders),
	)

	useEffect(() => {
		setRecentRoutes(mergeRecentRoutes(recentOrders))
	}, [recentOrders])

	useEffect(() => {
		let cancelled = false
		void fetchPublicMapSettings().then((s) => {
			if (!cancelled) {
				setMapSettings(s)
			}
		})
		return () => {
			cancelled = true
		}
	}, [])

	const settings = mapSettings ?? defaultMapSettings()
	const center: [number, number] = [
		settings.map.center.latitude,
		settings.map.center.longitude,
	]
	const maxBounds =
		settings.map.maxBounds != null
			? L.latLngBounds(settings.map.maxBounds[0], settings.map.maxBounds[1])
			: undefined

	const myIcon = useMemo(
		() =>
			new L.Icon({
				iconUrl: CustomIcon,
				iconSize: new L.Point(40, 40),
				iconAnchor: [20, 30],
			}),
		[],
	)

	const rememberPoint = useCallback((label: string, lat: number, lng: number) => {
		pushAddressHistory({ label, lat, lng })
	}, [])

	const rememberCurrentRoute = useCallback(
		(fromOverride?: SavedAddress, toOverride?: SavedAddress) => {
			const from =
				fromOverride ??
				(watch('from') && fromMarkerPos
					? { label: watch('from'), lat: fromMarkerPos.lat, lng: fromMarkerPos.lng }
					: null)
			const to =
				toOverride ??
				(watch('to') && toMarkerPos
					? { label: watch('to'), lat: toMarkerPos.lat, lng: toMarkerPos.lng }
					: null)
			if (!from || !to) return
			pushRouteHistory({ from, to })
			setRecentRoutes(mergeRecentRoutes(recentOrders))
		},
		[fromMarkerPos, recentOrders, toMarkerPos, watch],
	)

	const applyRoute = useCallback(
		async (route: RecentRouteItem) => {
			setGeoHint(null)
			setValue('from', route.from.label)
			setValue('to', route.to.label)

			let fromPoint: SavedAddress | null =
				route.from.lat != null && route.from.lng != null
					? { label: route.from.label, lat: route.from.lat, lng: route.from.lng }
					: null
			let toPoint: SavedAddress | null =
				route.to.lat != null && route.to.lng != null
					? { label: route.to.label, lat: route.to.lat, lng: route.to.lng }
					: null

			if (!fromPoint) fromPoint = await resolveAddressLabel(settings, route.from.label)
			if (!toPoint) toPoint = await resolveAddressLabel(settings, route.to.label)

			if (!fromPoint || !toPoint) {
				setGeoHint('Could not resolve this route. Try selecting addresses manually.')
				return
			}

			setValue('from', fromPoint.label)
			setValue('pickupLatitude', fromPoint.lat)
			setValue('pickupLongitude', fromPoint.lng)
			setFromMarkerPos({ lat: fromPoint.lat, lng: fromPoint.lng })
			setValue('to', toPoint.label)
			setValue('dropoutLatitude', toPoint.lat)
			setValue('dropoutLongitude', toPoint.lng)
			setToMarkerPos({ lat: toPoint.lat, lng: toPoint.lng })
			pushAddressHistory(fromPoint)
			pushAddressHistory(toPoint)
			pushRouteHistory({ from: fromPoint, to: toPoint })
			setRecentRoutes(mergeRecentRoutes(recentOrders))
		},
		[recentOrders, setValue, settings],
	)

	const applyFromMap = useCallback(
		async (lat: number, lng: number, target: MapPickTarget) => {
			setGeoHint(null)
			const bboxErr = validateBbox(settings, lat, lng)
			if (bboxErr) {
				setGeoHint(bboxErr)
				return
			}
			const useGoogle = settings.googleAddressSearch === true
			const rev = useGoogle
				? await reverseGeocodeGoogle(lat, lng)
				: await reverseGeocode(settings.nominatimApiUrl, lat, lng)
			if (!rev) {
				setGeoHint('Could not resolve address for this point. Try again later.')
				return
			}
			const addrText = useGoogle
				? (rev as GeocodeResolved).displayLine
				: formatNominatimAddress(rev as NominatimResult)
			const countryErr = useGoogle
				? validateCountryCodes(settings, {
						country_code: (rev as GeocodeResolved).countryCode ?? undefined,
				  })
				: validateCountryCodes(settings, (rev as NominatimResult).address ?? null)
			if (countryErr) {
				setGeoHint(countryErr)
				return
			}
			rememberPoint(addrText, lat, lng)
			if (target === 'from') {
				setValue('from', addrText)
				setValue('pickupLatitude', lat)
				setValue('pickupLongitude', lng)
				setFromMarkerPos({ lat, lng })
				if (toMarkerPos && watch('to')) {
					rememberCurrentRoute(
						{ label: addrText, lat, lng },
						{ label: watch('to'), lat: toMarkerPos.lat, lng: toMarkerPos.lng },
					)
				}
			} else {
				setValue('to', addrText)
				setValue('dropoutLatitude', lat)
				setValue('dropoutLongitude', lng)
				setToMarkerPos({ lat, lng })
				if (fromMarkerPos && watch('from')) {
					rememberCurrentRoute(
						{ label: watch('from'), lat: fromMarkerPos.lat, lng: fromMarkerPos.lng },
						{ label: addrText, lat, lng },
					)
				}
			}
		},
		[
			fromMarkerPos,
			rememberCurrentRoute,
			rememberPoint,
			setValue,
			settings,
			toMarkerPos,
			watch,
		],
	)

	const onMarkerDragEnd = useCallback(
		(target: MapPickTarget) => (e: L.DragEndEvent) => {
			const m = e.target
			if (!m || typeof m.getLatLng !== 'function') {
				return
			}
			const p = m.getLatLng()
			void applyFromMap(p.lat, p.lng, target)
		},
		[applyFromMap]
	)

	return (
		<div className={cn(styles.body, styles.whereActive)}>
			<input type="hidden" {...register('pickupLatitude')} />
			<input type="hidden" {...register('pickupLongitude')} />
			<input type="hidden" {...register('dropoutLatitude')} />
			<input type="hidden" {...register('dropoutLongitude')} />

			<div className={styles.left}>
				<div className={styles.top}>
					<div className={styles.routeWrapper}>
						<div className={styles.route} />
					</div>

					<div className={styles.whereFieldsOnly}>
						<div className={styles.input}>
							<span>From</span>
							<Controller
								control={control}
								name="from"
								render={({ field: { value, onChange } }) => (
									<AddressSearchInput
										mapSettings={settings}
										value={value}
										onChange={onChange}
										onGeoHint={setGeoHint}
										onAddressFieldFocus={() => {
											mapClickTargetRef.current = 'from'
										}}
										onSelect={(addr, lat, lng) => {
											setValue('pickupLatitude', lat)
											setValue('pickupLongitude', lng)
											setFromMarkerPos({ lat, lng })
											rememberPoint(addr, lat, lng)
											if (toMarkerPos && watch('to')) {
												rememberCurrentRoute(
													{ label: addr, lat, lng },
													{ label: watch('to'), lat: toMarkerPos.lat, lng: toMarkerPos.lng },
												)
											}
										}}
										onClear={() => {
											setFromMarkerPos(null)
											setValue('pickupLatitude', undefined)
											setValue('pickupLongitude', undefined)
										}}
										placeholder="From"
									/>
								)}
							/>
						</div>

						<div className={styles.input}>
							<span>To</span>
							<Controller
								control={control}
								name="to"
								render={({ field: { value, onChange } }) => (
									<AddressSearchInput
										mapSettings={settings}
										value={value}
										onChange={onChange}
										onGeoHint={setGeoHint}
										onAddressFieldFocus={() => {
											mapClickTargetRef.current = 'to'
										}}
										onSelect={(addr, lat, lng) => {
											setValue('dropoutLatitude', lat)
											setValue('dropoutLongitude', lng)
											setToMarkerPos({ lat, lng })
											rememberPoint(addr, lat, lng)
											if (fromMarkerPos && watch('from')) {
												rememberCurrentRoute(
													{ label: watch('from'), lat: fromMarkerPos.lat, lng: fromMarkerPos.lng },
													{ label: addr, lat, lng },
												)
											}
										}}
										onClear={() => {
											setToMarkerPos(null)
											setValue('dropoutLatitude', undefined)
											setValue('dropoutLongitude', undefined)
										}}
										placeholder="To"
									/>
								)}
							/>
						</div>
					</div>

					{!watch('from') || !watch('to') ? (
						<div className={styles.whereSwapPlaceholder} />
					) : (
						<div className={styles.buttonWrapper}>
							<button
								className={styles.button}
								type="button"
								onClick={() => {
									const curFrom = watch('from')
									const curTo = watch('to')

									if (!curFrom || !curTo) {
										return
									}

									setValue('from', curTo)
									setValue('to', curFrom)

									const pLat = watch('pickupLatitude')
									const pLng = watch('pickupLongitude')
									const dLat = watch('dropoutLatitude')
									const dLng = watch('dropoutLongitude')
									setValue('pickupLatitude', dLat)
									setValue('pickupLongitude', dLng)
									setValue('dropoutLatitude', pLat)
									setValue('dropoutLongitude', pLng)

									setFromMarkerPos(toMarkerPos)
									setToMarkerPos(fromMarkerPos)
								}}
							>
								<Icon type="swap" size={16} />
							</button>
						</div>
					)}

					{geoHint ? (
						<div className={styles.whereGeoHintRow}>
							<p className={styles.whereGeoHint}>{geoHint}</p>
						</div>
					) : null}
				</div>

				<div className={styles.hr} />

				<div className={styles.history}>
					{recentRoutes.length === 0 ? (
						<div className={styles.empty}>no recent routes</div>
					) : (
						recentRoutes.map((route) => (
							<div className={styles.item} key={routeKeyOf(route)}>
								<span title={formatRecentRouteLabel(route)}>
									{formatRecentRouteLabel(route)}
								</span>
								<button type="button" onClick={() => void applyRoute(route)}>
									Pielietot
								</button>
							</div>
						))
					)}
				</div>
			</div>

			<div className={styles.right}>
				<Suspense
					fallback={
						<div className="flex items-center justify-center h-full w-full">Loading...</div>
					}
				>
					<MapContainer
						// @ts-ignore react-leaflet v5-rc vs types
						center={center}
						zoom={settings.map.zoom}
						maxBounds={maxBounds}
						maxBoundsViscosity={maxBounds != null ? 0.85 : undefined}
						style={{ width: '100%', height: '100%' }}
					>
						<TileLayer
							// @ts-ignore
							attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
							url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
						/>
						<MapController fromPos={fromMarkerPos} toPos={toMarkerPos} />
						<MapClickHandler
							onClick={(lat, lng) => {
								void applyFromMap(lat, lng, mapClickTargetRef.current)
							}}
						/>
						{fromMarkerPos && (
							<Marker
								// @ts-ignore
								icon={myIcon}
								draggable
								position={[fromMarkerPos.lat, fromMarkerPos.lng]}
								eventHandlers={{
									dragend: onMarkerDragEnd('from'),
								}}
							/>
						)}
						{toMarkerPos && (
							<Marker
								// @ts-ignore
								icon={myIcon}
								draggable
								position={[toMarkerPos.lat, toMarkerPos.lng]}
								eventHandlers={{
									dragend: onMarkerDragEnd('to'),
								}}
							/>
						)}
					</MapContainer>
				</Suspense>
			</div>
		</div>
	)
}

const MapClickHandler: FC<{ onClick: (lat: number, lng: number) => void }> = ({ onClick }) => {
	useMapEvents({
		click(e: L.LeafletMouseEvent) {
			onClick(e.latlng.lat, e.latlng.lng)
		},
	})
	return null
}

interface AddressSearchInputProps {
	mapSettings: PublicMapSettings
	value: string
	onChange: (val: string) => void
	onSelect: (address: string, lat: number, lng: number) => void
	onClear?: () => void
	onGeoHint: (msg: string | null) => void
	/** Map clicks apply to this field while it is (or was last) focused. */
	onAddressFieldFocus?: () => void
	placeholder: string
	disabled?: boolean
}

const AddressSearchInput: FC<AddressSearchInputProps> = ({
	mapSettings,
	value,
	onChange,
	onSelect,
	onClear,
	onGeoHint,
	onAddressFieldFocus,
	placeholder,
	disabled,
}) => {
	const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([])
	const [showSuggestions, setShowSuggestions] = useState(false)
	const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
	const sessionTokenRef = useRef<string>(newGeocodeSessionToken())

	const showHistorySuggestions = () => {
		const history = getAddressHistory().slice(0, WHERE_HISTORY_LIMIT)
		const rows: AddressSuggestion[] = history.map((item) => ({
			source: 'history' as const,
			data: item,
		}))
		setSuggestions(rows)
		setShowSuggestions(rows.length > 0)
	}

	const searchAddress = async (query: string) => {
		if (query.length < 2) {
			showHistorySuggestions()
			return
		}
		const googleMode = mapSettings.googleAddressSearch === true
		try {
			if (googleMode) {
				const res = await fetch(PUBLIC_GEOCODE_AUTOCOMPLETE_URL, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify({
						input: query,
						sessionToken: sessionTokenRef.current,
					}),
				})
				if (!res.ok) {
					showHistorySuggestions()
					return
				}
				const json = (await res.json()) as { predictions?: GeocodePrediction[] }
				const list = Array.isArray(json.predictions) ? json.predictions : []
				if (list.length === 0) {
					showHistorySuggestions()
					return
				}
				setSuggestions(list.map((p) => ({ source: 'google' as const, data: p })))
				setShowSuggestions(true)
				return
			}

			const root = mapSettings.nominatimApiUrl.replace(/\/$/, '')
			const params = new URLSearchParams({
				q: query,
				format: 'json',
				addressdetails: '1',
				limit: '8',
				'accept-language': 'en',
			})
			if (mapSettings.nominatimCountryCodes?.trim()) {
				params.set(
					'countrycodes',
					mapSettings.nominatimCountryCodes.replace(/\s+/g, '').toLowerCase()
				)
			}
			const bb = mapSettings.boundingBox
			if (bb) {
				params.set(
					'viewbox',
					`${bb.minLongitude},${bb.maxLatitude},${bb.maxLongitude},${bb.minLatitude}`
				)
				params.set('bounded', '1')
			}
			const res = await fetch(`${root}/search?${params}`, {
				headers: { 'User-Agent': NOMINATIM_BROWSER_USER_AGENT },
			})
			const data: NominatimResult[] = await res.json()
			const rows = Array.isArray(data) ? data : []
			if (rows.length === 0) {
				showHistorySuggestions()
				return
			}
			setSuggestions(rows.map((r) => ({ source: 'nominatim' as const, data: r })))
			setShowSuggestions(true)
		} catch {
			showHistorySuggestions()
		}
	}

	const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
		const val = e.target.value
		onChange(val)
		onGeoHint(null)
		if (!val.trim()) {
			if (mapSettings.googleAddressSearch === true) {
				sessionTokenRef.current = newGeocodeSessionToken()
			}
			onClear?.()
			showHistorySuggestions()
			return
		}
		if (debounceRef.current) {
			clearTimeout(debounceRef.current)
		}
		debounceRef.current = setTimeout(() => void searchAddress(val), 400)
	}

	const handleSelect = async (item: AddressSuggestion) => {
		if (item.source === 'history') {
			const saved = item.data
			onChange(saved.label)
			onSelect(saved.label, saved.lat, saved.lng)
			onGeoHint(null)
			pushAddressHistory(saved)
			setSuggestions([])
			setShowSuggestions(false)
			return
		}

		if (item.source === 'nominatim') {
			const result = item.data
			const lat = parseFloat(result.lat)
			const lng = parseFloat(result.lon)
			const bboxErr = validateBbox(mapSettings, lat, lng)
			if (bboxErr) {
				onGeoHint(bboxErr)
				setSuggestions([])
				setShowSuggestions(false)
				return
			}
			const countryErr = validateCountryCodes(mapSettings, result.address ?? null)
			if (countryErr) {
				onGeoHint(countryErr)
				setSuggestions([])
				setShowSuggestions(false)
				return
			}
			const addr = formatNominatimAddress(result)
			onChange(addr)
			onSelect(addr, lat, lng)
			onGeoHint(null)
			pushAddressHistory({ label: addr, lat, lng })
			setSuggestions([])
			setShowSuggestions(false)
			return
		}

		const prediction = item.data
		let res: Response
		try {
			res = await fetch(PUBLIC_GEOCODE_PLACE_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({
					placeId: prediction.placeId,
					sessionToken: sessionTokenRef.current,
				}),
			})
		} catch {
			onGeoHint('Could not resolve this address. Try again later.')
			setSuggestions([])
			setShowSuggestions(false)
			return
		}
		if (!res.ok) {
			let msg = 'Could not resolve this address. Try again later.'
			try {
				const errBody = (await res.json()) as { error?: string }
				if (typeof errBody.error === 'string' && errBody.error) {
					msg = errBody.error
				}
			} catch {
				/* ignore */
			}
			onGeoHint(msg)
			setSuggestions([])
			setShowSuggestions(false)
			return
		}
		const dto = (await res.json()) as GeocodeResolved
		const lat = dto.latitude
		const lng = dto.longitude
		const bboxErr = validateBbox(mapSettings, lat, lng)
		if (bboxErr) {
			onGeoHint(bboxErr)
			setSuggestions([])
			setShowSuggestions(false)
			return
		}
		const countryErr = validateCountryCodes(mapSettings, {
			country_code: dto.countryCode ?? undefined,
		})
		if (countryErr) {
			onGeoHint(countryErr)
			setSuggestions([])
			setShowSuggestions(false)
			return
		}
		onChange(dto.displayLine)
		onSelect(dto.displayLine, lat, lng)
		onGeoHint(null)
		pushAddressHistory({ label: dto.displayLine, lat, lng })
		setSuggestions([])
		setShowSuggestions(false)
		sessionTokenRef.current = newGeocodeSessionToken()
	}

	const suggestionLabel = (s: AddressSuggestion): string => {
		if (s.source === 'history') return s.data.label
		if (s.source === 'google') return s.data.description
		return s.data.display_name
	}

	const suggestionKey = (s: AddressSuggestion): string => {
		if (s.source === 'history') return `h-${s.data.label}-${s.data.lat}-${s.data.lng}`
		if (s.source === 'google') return `g-${s.data.placeId}`
		return `n-${s.data.place_id}`
	}

	return (
		<div className={styles.addressInputWrapper}>
			<div className={cn(styles.addressInputInner, { [styles.addressDisabled]: disabled })}>
				<input
					className={styles.addressInput}
					value={value}
					onChange={handleChange}
					onKeyDown={(e) => {
						if (e.key === 'Enter' && suggestions.length > 0) {
							e.preventDefault()
							void handleSelect(suggestions[0])
						}
					}}
					onFocus={() => {
						onAddressFieldFocus?.()
						if ((value?.trim().length ?? 0) < 2) {
							showHistorySuggestions()
						} else if (suggestions.length > 0) {
							setShowSuggestions(true)
						} else {
							showHistorySuggestions()
						}
					}}
					onBlur={() => setTimeout(() => setShowSuggestions(false), 150)}
					placeholder={placeholder}
					disabled={disabled}
					autoComplete="off"
				/>
			</div>
			{showSuggestions && suggestions.length > 0 && (
				<div className={styles.suggestions}>
					{suggestions.map((s) => (
						<div
							key={suggestionKey(s)}
							className={styles.suggestion}
							onMouseDown={() => void handleSelect(s)}
						>
							<Icon type="mark_map" size={14} className={styles.suggestionIcon} />
							<span>{suggestionLabel(s)}</span>
						</div>
					))}
				</div>
			)}
		</div>
	)
}

const MapController: FC<{
	fromPos: { lat: number; lng: number } | null
	toPos: { lat: number; lng: number } | null
}> = ({ fromPos, toPos }) => {
	const map = useMap()

	useEffect(() => {
		if (fromPos && toPos) {
			const bounds = L.latLngBounds([fromPos.lat, fromPos.lng], [toPos.lat, toPos.lng])
			map.fitBounds(bounds, { padding: [50, 50] })
		} else if (fromPos) {
			map.setView([fromPos.lat, fromPos.lng], 12)
		} else if (toPos) {
			map.setView([toPos.lat, toPos.lng], 12)
		}
	}, [fromPos, toPos, map])

	return null
}
