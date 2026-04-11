import { ChangeEvent, type FC, Suspense, useCallback, useEffect, useRef, useState } from 'react'
import {
	Control,
	Controller,
	UseFormRegister,
	UseFormSetValue,
	UseFormWatch,
} from 'react-hook-form'
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet'

import { DEFAULT_LAT, DEFAULT_LNG, PUBLIC_MAP_SETTINGS_URL } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'
// @ts-ignore
import L from 'leaflet'

// @ts-ignore
import CustomIcon from '../OrderCard/CustomMarker.svg'

import styles from './ModalContent.module.css'

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
}

interface WhereContentProps {
	watch: UseFormWatch<FormValues>
	control: Control<FormValues, any, FormValues>
	setValue: UseFormSetValue<FormValues>
	register: UseFormRegister<FormValues>
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

	const myIcon = new L.Icon({
		iconUrl: CustomIcon,
		iconSize: new L.Point(40, 40),
		iconAnchor: [20, 30],
	})

	const applyFromMap = useCallback(
		async (lat: number, lng: number, target: MapPickTarget) => {
			setGeoHint(null)
			const bboxErr = validateBbox(settings, lat, lng)
			if (bboxErr) {
				setGeoHint(bboxErr)
				return
			}
			const rev = await reverseGeocode(settings.nominatimApiUrl, lat, lng)
			if (!rev) {
				setGeoHint('Could not resolve address for this point. Try again later.')
				return
			}
			const addrText = formatNominatimAddress(rev)
			const countryErr = validateCountryCodes(settings, rev.address ?? null)
			if (countryErr) {
				setGeoHint(countryErr)
				return
			}
			if (target === 'from') {
				setValue('from', addrText)
				setValue('pickupLatitude', lat)
				setValue('pickupLongitude', lng)
				setFromMarkerPos({ lat, lng })
			} else {
				setValue('to', addrText)
				setValue('dropoutLatitude', lat)
				setValue('dropoutLongitude', lng)
				setToMarkerPos({ lat, lng })
			}
		},
		[setValue, settings]
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

			<div className={cn(styles.left, { [styles.noRoutes]: true })}>
				<div className={styles.top}>
					<div className={styles.routeWrapper}>
						<div className={styles.route} />
					</div>

					<div className={styles.inputs}>
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
										onSelect={(_addr, lat, lng) => {
											setValue('pickupLatitude', lat)
											setValue('pickupLongitude', lng)
											setFromMarkerPos({ lat, lng })
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
										onSelect={(_addr, lat, lng) => {
											setValue('dropoutLatitude', lat)
											setValue('dropoutLongitude', lng)
											setToMarkerPos({ lat, lng })
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

						{geoHint ? (
							<div className={styles.whereGeoHintRow}>
								<p className={styles.whereGeoHint}>{geoHint}</p>
							</div>
						) : null}
					</div>

					{!watch('from') || !watch('to') ? (
						<div />
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
				</div>

				<div />
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
	const [suggestions, setSuggestions] = useState<NominatimResult[]>([])
	const [showSuggestions, setShowSuggestions] = useState(false)
	const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

	const searchAddress = async (query: string) => {
		if (query.length < 2) {
			setSuggestions([])
			setShowSuggestions(false)
			return
		}
		try {
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
			setSuggestions(Array.isArray(data) ? data : [])
			setShowSuggestions(Array.isArray(data) && data.length > 0)
		} catch {
			setSuggestions([])
		}
	}

	const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
		const val = e.target.value
		onChange(val)
		onGeoHint(null)
		if (!val.trim()) {
			setSuggestions([])
			setShowSuggestions(false)
			onClear?.()
			return
		}
		if (debounceRef.current) {
			clearTimeout(debounceRef.current)
		}
		debounceRef.current = setTimeout(() => searchAddress(val), 400)
	}

	const handleSelect = (result: NominatimResult) => {
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
		setSuggestions([])
		setShowSuggestions(false)
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
							handleSelect(suggestions[0])
						}
					}}
					onFocus={() => {
						onAddressFieldFocus?.()
						if (suggestions.length > 0) {
							setShowSuggestions(true)
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
						<div key={s.place_id} className={styles.suggestion} onMouseDown={() => handleSelect(s)}>
							{s.display_name}
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
