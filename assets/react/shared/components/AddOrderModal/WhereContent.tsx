import { ChangeEvent, type FC, Suspense, useEffect, useRef, useState } from 'react'
import {
	Control,
	Controller,
	UseFormRegister,
	UseFormSetValue,
	UseFormWatch,
} from 'react-hook-form'
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet'

import { DEFAULT_LAT, DEFAULT_LNG } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'
// @ts-ignore
import L from 'leaflet'

// @ts-ignore
import CustomIcon from '../OrderCard/CustomMarker.svg'

import styles from './ModalContent.module.css'

import { FormValues } from './types'

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

export const WhereContent: FC<WhereContentProps> = ({
	watch,
	control,
	setValue,
	register,
	defaultPosition,
}) => {
	const [fromMarkerPos, setFromMarkerPos] = useState<{ lat: number; lng: number } | null>(
		defaultPosition?.from || null
	)
	const [toMarkerPos, setToMarkerPos] = useState<{ lat: number; lng: number } | null>(
		defaultPosition?.to || null
	)

	const myIcon = new L.Icon({
		iconUrl: CustomIcon,
		iconSize: new L.Point(40, 40),
		iconAnchor: [20, 30],
	})

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
										value={value}
										onChange={onChange}
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
										value={value}
										onChange={onChange}
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

									if (!curFrom || !curTo) return

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
						// @ts-ignore
						center={[DEFAULT_LAT, DEFAULT_LNG]}
						zoom={10}
						style={{ width: '100%', height: '100%' }}
					>
						<TileLayer
							// @ts-ignore
							attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
							url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
						/>
						<MapController fromPos={fromMarkerPos} toPos={toMarkerPos} />
						{fromMarkerPos && (
							<Marker
								// @ts-ignore
								icon={myIcon}
								position={[fromMarkerPos.lat, fromMarkerPos.lng]}
							/>
						)}
						{toMarkerPos && (
							<Marker
								// @ts-ignore
								icon={myIcon}
								position={[toMarkerPos.lat, toMarkerPos.lng]}
							/>
						)}
					</MapContainer>
				</Suspense>
			</div>
		</div>
	)
}

// ─────────────────────────────────────────────────────────────────────────────

interface NominatimResult {
	place_id: number
	display_name: string
	lat: string
	lon: string
	address: {
		road?: string
		street?: string
		house_number?: string
		postcode?: string
		city?: string
		town?: string
		village?: string
		municipality?: string
		country?: string
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

	if (addr.country) parts.push(addr.country)

	return parts.length > 0 ? parts.join(', ') : result.display_name
}

interface AddressSearchInputProps {
	value: string
	onChange: (val: string) => void
	onSelect: (address: string, lat: number, lng: number) => void
	onClear?: () => void
	placeholder: string
	disabled?: boolean
}

const AddressSearchInput: FC<AddressSearchInputProps> = ({
	value,
	onChange,
	onSelect,
	onClear,
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
			const params = new URLSearchParams({
				q: query,
				format: 'json',
				addressdetails: '1',
				limit: '5',
				'accept-language': 'en',
			})
			const res = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
				headers: { 'User-Agent': 'HeviiTransportApp/1.0' },
			})
			const data: NominatimResult[] = await res.json()
			setSuggestions(data)
			setShowSuggestions(data.length > 0)
		} catch {
			setSuggestions([])
		}
	}

	const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
		const val = e.target.value
		onChange(val)
		if (!val.trim()) {
			setSuggestions([])
			setShowSuggestions(false)
			onClear?.()
			return
		}
		if (debounceRef.current) clearTimeout(debounceRef.current)
		debounceRef.current = setTimeout(() => searchAddress(val), 400)
	}

	const handleSelect = (result: NominatimResult) => {
		const addr = formatNominatimAddress(result)
		onChange(addr)
		onSelect(addr, parseFloat(result.lat), parseFloat(result.lon))
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
					onFocus={() => suggestions.length > 0 && setShowSuggestions(true)}
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
