import { type ChangeEvent, type Dispatch, type FC, type SetStateAction, Suspense, useEffect, useRef, useState } from 'react'
import { DateRange } from 'react-day-picker'
import { Control, Controller, SubmitHandler, useForm } from 'react-hook-form'
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet'
// @ts-ignore
import L from 'leaflet'
// @ts-ignore
import CustomIcon from '../../../../../shared/components/OrderCard/CustomMarker.svg'

import { apiCreateOrder } from '@api/orderApi'
import { YearsType, months, years } from '@config/constants'
import { useAuth } from '@hooks/useAuth'
import { Button } from '@ui/Button/Button'
import { Calendar } from '@ui/Calendar/Calendar'
import { Icon } from '@ui/Icon/Icon'
import { Input } from '@ui/Input/Input'
import { Select } from '@ui/Select/Select'
import { Slider } from '@ui/Slider/Slider'
import { Switch } from '@ui/Switch/Switch'
import { Textarea } from '@ui/Textarea/Textarea'
import { cn } from '@utils/cn'
import { addDays } from 'date-fns'

// @ts-ignore
import pickup_ready from './pickup_ready.png'

import styles from './ModalContent.module.css'

import { CalculateModalType } from '../../Requests'
import { InputButton } from '../InputButton/InputButton'

interface ModalContentProps {
	activeButton: CalculateModalType
	setActiveButton: Dispatch<SetStateAction<CalculateModalType>>
	// latestRoutes?: { from: string; to: string }[]
}

type FormValues = {
	// what
	cargoType: 'palette' | 'irregular_cargo'
	amount: number
	width: number
	length: number
	maxHeight: number
	maxWeight: number
	name: string
	comments: string
	stackabilityPossible: boolean
	truckWithLift: boolean
	// where
	from: string
	to: string
	pickupLatitude: number | undefined
	pickupLongitude: number | undefined
	dropoutLatitude: number | undefined
	dropoutLongitude: number | undefined
	// when
	scheduleType: 'pickup_ready' | 'pickup_later' | 'deliver_at'
	month: number
	year: YearsType
	date?: string
	timeFrom?: string
	timeTo?: string
}

const DEFAULT_LAT = 56.946845
const DEFAULT_LNG = 24.106075

export const ModalContent: FC<ModalContentProps> = ({
	activeButton,
	setActiveButton,
	// latestRoutes,
}) => {
	const currentDate = new Date()
	const currentMonthZeroBased = currentDate.getMonth()
	const currentYear = currentDate.getFullYear()

	const { getValidAccessToken } = useAuth()

	const [isCalculating, setIsCalculating] = useState(false)
	const [submitError, setSubmitError] = useState<string | null>(null)

	const [fromMarkerPos, setFromMarkerPos] = useState<{ lat: number; lng: number } | null>(null)
	const [toMarkerPos, setToMarkerPos] = useState<{ lat: number; lng: number } | null>(null)

	const myIcon = new L.Icon({
		iconUrl: CustomIcon,
		iconSize: new L.Point(40, 40),
		iconAnchor: [20, 30],
	})

	const [dateRange, setDateRange] = useState<DateRange | undefined>({
		from: new Date(new Date().getFullYear(), 0, 12),
		to: addDays(new Date(new Date().getFullYear(), 0, 12), 30),
	})

	const bookedDates = Array.from(
		{ length: 15 },
		(_, i) => new Date(new Date().getFullYear(), 2, 12 + i)
	)

	const [month, setMonth] = useState(() => {
		const d = new Date()
		d.setDate(1)
		return d
	})

	const onSelectMonth = (monthIndex: number) => {
		setMonth((prev) => {
			const next = new Date(prev)
			next.setMonth(monthIndex)
			next.setDate(1)
			return next
		})
	}

	const handleYearChange = (year: number) => {
		setMonth((prev) => {
			const next = new Date(prev)
			next.setFullYear(year)
			next.setDate(1)
			return next
		})
	}

	const { control, register, handleSubmit, watch, setValue } = useForm<FormValues>({
		defaultValues: {
			// what
			cargoType: 'palette',
			amount: 0,
			width: 120,
			length: 80,
			maxHeight: 0,
			maxWeight: 0,
			name: '',
			comments: '',
			stackabilityPossible: false,
			truckWithLift: true,
			// where
			from: '',
			to: '',
			pickupLatitude: undefined,
			pickupLongitude: undefined,
			dropoutLatitude: undefined,
			dropoutLongitude: undefined,
			// when
			scheduleType: 'pickup_ready',
			// scheduleType: 'pickup_later',
			month: currentMonthZeroBased,
			year: currentYear.toString() as YearsType,
			// date: '',
			// timeFrom: '',
			// timeTo: '',
		},
	})

	const onSubmit: SubmitHandler<FormValues> = async (values) => {
		setSubmitError(null)
		setIsCalculating(true)

		try {
			const token = await getValidAccessToken()
			if (!token) {
				setSubmitError('Session expired. Please log in again.')
				setIsCalculating(false)
				return
			}

			// Map scheduleType + dateRange → pickupDate / deliveryDate
			const formatDate = (d: Date | undefined): string | null =>
				d ? d.toISOString().split('T')[0] : null

			let pickupDate: string | null = null
			let deliveryDate: string | null = null
			if (values.scheduleType === 'pickup_later') {
				pickupDate = formatDate(dateRange?.from)
			} else if (values.scheduleType === 'deliver_at') {
				deliveryDate = formatDate(dateRange?.from)
			}

			// Map cargoType string → Cargo.type int
			const cargoTypeMap: Record<string, 1 | 2> = {
				palette:         1,
				irregular_cargo: 2,
			}

			// Compose dimensionsCm: "widthxlengthxheight"
			const dimensionsCm =
				values.width || values.length || values.maxHeight
					? `${values.width}x${values.length}x${values.maxHeight}`
					: null

			const result = await apiCreateOrder(token, {
				pickupAddress:    values.from,
				dropoutAddress:   values.to,
				pickupLatitude:   values.pickupLatitude ?? null,
				pickupLongitude:  values.pickupLongitude ?? null,
				dropoutLatitude:  values.dropoutLatitude ?? null,
				dropoutLongitude: values.dropoutLongitude ?? null,
				notes:            values.comments || null,
				pickupTimeFrom:   values.timeFrom || null,
				pickupTimeTo:     values.timeTo || null,
				pickupDate,
				deliveryDate,
				cargo: {
					type:              cargoTypeMap[values.cargoType] ?? 1,
					quantity:          values.amount,
					weightKg:          values.maxWeight,
					dimensionsCm,
					name:              values.name,
					stackable:         values.stackabilityPossible,
					manipulatorNeeded: values.truckWithLift,
				},
			})

			// Redirect to the newly created order after 2 seconds
			setTimeout(() => {
				window.location.href = `/user/orders/${result.id}`
			}, 2000)
		} catch (err) {
			setSubmitError(err instanceof Error ? err.message : 'Something went wrong')
			setIsCalculating(false)
		}
	}

	if (isCalculating)
		return (
			<div className={styles.isCalculating}>
				<div className={styles.icon}>
					<Icon type="big_box" size={40} />
				</div>

				<div className={styles.wrapper}>
					<div className={styles.title}>Calculating...</div>
					<div className={styles.subtitle}>
						{watch('name') || 'Freight'} · {watch('from')} → {watch('to')}
					</div>
				</div>
			</div>
		)

	return (
		<form className={styles.modal} onSubmit={(e) => e.preventDefault()}>
			<div className={styles.header}>
				<div className={styles.title}>
					Freight Item <span>(1)</span>
				</div>

				<div
					className={cn(styles.inputButtons, {
						[styles.whatActive]: activeButton === 'what',
						[styles.whereActive]: activeButton === 'where',
						[styles.whenActive]: activeButton === 'when',
					})}
				>
					<InputButton
						label={
							activeButton !== 'what' ? (
								'What'
							) : (
								<>
									<div className={styles.icon}>
										<Icon type="box" size={20} />
									</div>
									What?
								</>
							)
						}
						placeholder={
							activeButton === 'what' ? 'Add cargo and amount of items' : 'Fill cargo parameters'
						}
						onClick={() => setActiveButton('what')}
						className={cn(styles.inputButton, { [styles.active]: activeButton === 'what' })}
					/>
					<InputButton
						label={
							activeButton !== 'where' ? (
								'Where'
							) : (
								<>
									<div className={styles.icon}>
										<Icon type="mark_map" size={20} />
									</div>
									Where?
								</>
							)
						}
						placeholder={activeButton === 'where' ? 'Select destination' : 'Enter destination'}
						onClick={() => setActiveButton('where')}
						className={cn(styles.inputButton, { [styles.active]: activeButton === 'where' })}
					/>
					<InputButton
						label={
							activeButton !== 'when' ? (
								'When'
							) : (
								<>
									<div className={styles.icon}>
										<Icon type="clock_1" size={20} />
									</div>
									When?
								</>
							)
						}
						placeholder={activeButton === 'when' ? 'Add Date and Time' : 'Add date'}
						onClick={() => setActiveButton('when')}
						className={cn(styles.inputButton, { [styles.active]: activeButton === 'when' })}
					/>

					<Button
						type="button"
						className="w-[130px] shrink-0 ml-3"
						onClick={handleSubmit(onSubmit)}
					>
						Calculate
					</Button>
				</div>

				{submitError && (
					<div className={styles.submitError}>{submitError}</div>
				)}
			</div>

			{activeButton === 'what' && (
				<div className={cn(styles.body, styles.whatActive)}>
					<div className={styles.left}>
						<div className={styles.top}>
							<Controller
								control={control}
								name="cargoType"
								render={({ field: { value, onChange } }) => (
									<Select
										color="green"
										defaultValue={value}
										value={value}
										onChange={onChange}
										values={[
											{ label: 'Palette', value: 'palette' },
											{ label: 'Irregular cargo', value: 'irregular_cargo' },
										]}
									/>
								)}
							/>

							<div className={styles.amount}>
								<span>Amount</span>

								<div className={styles.wrapper}>
									<button
										type="button"
										disabled={watch('amount') === 0}
										className={styles.button}
										onClick={() => {
											const v = watch('amount')
											setValue('amount', v === 0 ? 0 : v - 1)
										}}
									>
										–
									</button>
									<span>{watch('amount')}</span>
									<button
										type="button"
										className={styles.button}
										onClick={() => {
											const v = watch('amount')
											setValue('amount', v + 1)
										}}
									>
										+
									</button>
								</div>
							</div>
						</div>

						<div className={styles.items}>
							<CustomSlider
								control={control}
								name="width"
								label="Width"
								min={0}
								max={1000}
								unit="cm"
							/>

							<CustomSlider
								control={control}
								name="length"
								label="Length"
								min={0}
								max={1000}
								unit="cm"
							/>

							<CustomSlider
								control={control}
								name="maxHeight"
								label="Max Height"
								min={0}
								max={200}
								description="up to 200 cm"
								unit="cm"
							/>

							<CustomSlider
								control={control}
								name="maxWeight"
								label="Max Weight"
								min={0}
								max={500}
								description="up to 500 kg"
								unit="kg"
							/>
						</div>
					</div>

					<div className={styles.right}>
						<Input control={control} name="name" placeholder="Add commodity name" />
						<Textarea register={register('comments')} placeholder="Add comments" rows={7} />

						<div className={styles.switches}>
							<Controller
								control={control}
								name="stackabilityPossible"
								render={({ field: { value, onChange } }) => (
									<Switch checked={value} onChange={onChange} label="Stackability possible" />
								)}
							/>
							<Controller
								control={control}
								name="truckWithLift"
								render={({ field: { value, onChange } }) => (
									<Switch checked={value} onChange={onChange} label="Truck with Lift" />
								)}
							/>
						</div>
					</div>
				</div>
			)}

		{activeButton === 'where' && (
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
											disabled={!fromMarkerPos}
										/>
									)}
								/>
							</div>
						</div>

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
							style={{ width: '100%', height: 'calc(100% + 20px)' }}
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
		)}

			{activeButton === 'when' && (
				<div className={cn(styles.body, styles.whenActive)}>
					{/*  */}
					<div
						className={cn(styles.left, {
							[styles.pickupReady]: watch('scheduleType') === 'pickup_ready',
						})}
					>
						<div className={styles.buttons}>
							<button
								type="button"
								className={cn(styles.button, {
									[styles.active]: watch('scheduleType') === 'pickup_ready',
								})}
								onClick={() => setValue('scheduleType', 'pickup_ready')}
							>
								Pickup ready
							</button>
							<button
								type="button"
								className={cn(styles.button, {
									[styles.active]: watch('scheduleType') === 'pickup_later',
								})}
								onClick={() => setValue('scheduleType', 'pickup_later')}
							>
								Pickup later
							</button>
							<button
								type="button"
								className={cn(styles.button, {
									[styles.active]: watch('scheduleType') === 'deliver_at',
								})}
								onClick={() => setValue('scheduleType', 'deliver_at')}
							>
								Deliver at
							</button>
						</div>
					</div>

					{/*  */}
					<div
						className={cn(styles.center, {
							[styles.pickupReady]: watch('scheduleType') === 'pickup_ready',
							[styles.notPickupReady]: watch('scheduleType') !== 'pickup_ready',
							// [styles.pickupLater]: watch('scheduleType') === 'pickup_later',
							// [styles.deliverAt]: watch('scheduleType') === 'deliver_at',
						})}
					>
						{watch('scheduleType') === 'pickup_ready' && (
							<div className={styles.centerContent}>
								<img src={pickup_ready} alt="" />

								<div className={styles.icon}>
									<Icon type="check_circle_2" size={26} />
								</div>
							</div>
						)}

						{watch('scheduleType') !== 'pickup_ready' && (
							<div className={styles.centerContent}>
								<div className={styles.top}>
									<Controller
										control={control}
										name="month"
										render={({ field: { value, onChange } }) => (
											<Select
												color="green"
												defaultValue={value.toString()}
												value={value.toString()}
												onChange={(v) => {
													onChange(v)
													onSelectMonth(Number(v))
												}}
												values={months.map((month, index) => ({
													label: month.charAt(0).toUpperCase() + month.slice(1),
													value: index.toString(),
												}))}
											/>
										)}
									/>

									<Controller
										control={control}
										name="year"
										render={({ field: { value, onChange } }) => (
											<Select
												color="green"
												defaultValue={value}
												value={value}
												onChange={(v) => {
													onChange(v)
													handleYearChange(Number(v))
												}}
												values={years.map((year) => ({ label: year, value: year }))}
											/>
										)}
									/>
								</div>
								<div className={styles.calendar}>
									<Calendar
										mode="range"
										//
										month={month}
										setMonth={setMonth}
										//
										selected={dateRange}
										onSelect={setDateRange}
										className="rounded-lg border"
										//
										disabled={bookedDates}
										modifiers={{
											booked: bookedDates,
										}}
										modifiersClassNames={{
											booked: '[&>button]:line-through opacity-100',
										}}
									/>
								</div>
							</div>
						)}
					</div>

					{/*  */}
					<div className={styles.right}>
						<div className={styles.title}>Loading window</div>
						<div className={styles.timeWrapper}>
							<div className={styles.time}>
								<div className={styles.title}>from</div>
								<CustomTimeInput control={control} name="timeFrom" />
							</div>

							<div className={styles.time}>
								<div className={styles.title}>to</div>
								<CustomTimeInput control={control} name="timeTo" />
							</div>
						</div>
					</div>
				</div>
			)}
		</form>
	)
}

// ─── Nominatim geocoding helpers ─────────────────────────────────────────────

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

// ─── MapController — fits map bounds when markers change ──────────────────────

const MapController: FC<{
	fromPos: { lat: number; lng: number } | null
	toPos: { lat: number; lng: number } | null
}> = ({ fromPos, toPos }) => {
	const map = useMap()

	useEffect(() => {
		if (fromPos && toPos) {
			const bounds = L.latLngBounds(
				[fromPos.lat, fromPos.lng],
				[toPos.lat, toPos.lng]
			)
			map.fitBounds(bounds, { padding: [50, 50] })
		} else if (fromPos) {
			map.setView([fromPos.lat, fromPos.lng], 12)
		} else if (toPos) {
			map.setView([toPos.lat, toPos.lng], 12)
		}
	}, [fromPos, toPos, map])

	return null
}

// ─── AddressSearchInput — input with Nominatim autocomplete suggestions ───────

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
						<div
							key={s.place_id}
							className={styles.suggestion}
							onMouseDown={() => handleSelect(s)}
						>
							{s.display_name}
						</div>
					))}
				</div>
			)}
		</div>
	)
}

// ─────────────────────────────────────────────────────────────────────────────

const CustomSlider = ({
	label,
	max,
	description,
	min = 0,
	step = 1,
	unit,
	control,
	name,
}: {
	label: string
	max: number
	description?: string
	min?: number
	step?: number
	unit: string
	control: Control<FormValues, any, FormValues>
	name: keyof FormValues
}) => {
	return (
		<Controller
			control={control}
			name={name}
			render={({ field: { value, onChange } }) => (
				<div className={styles.customSlider}>
					<div className="relative">
						{description && <div className={styles.description}>{description}</div>}

						<Slider
							label={label}
							value={Number(value) || 0}
							min={min}
							max={max}
							step={step}
							setValue={onChange}
						/>
					</div>

					<div className={cn(styles.inputWrapper, { [styles.disabled]: !value })}>
						<input
							className={styles.input}
							value={value?.toString() || ''}
							onChange={(e) => {
								const v = Number(e.target.value)
								onChange(v > max ? max : v < min ? min : v)
							}}
							type="number"
							min={min}
							max={max}
						/>
						{unit}
					</div>
				</div>
			)}
		/>
	)
}

const CustomTimeInput = ({
	control,
	name,
}: {
	control: Control<FormValues, any, FormValues>
	name: keyof FormValues
}) => {
	const hoursRef = useRef(null)
	const minutesRef = useRef(null)

	return (
		<Controller
			control={control}
			name={name}
			render={({ field: { value, onChange } }) => {
				const [hours, minutes] = (value?.toString() || '00:00').split(':')
				return (
					<div className={styles.customTimeInput}>
						<input
							min={0}
							max={23}
							type="number"
							ref={hoursRef}
							value={hours}
							onChange={(e) => {
								const v = Number(e.target.value)
								const h = v > 23 ? 23 : v < 0 ? 0 : v
								onChange(`${h < 10 ? `0${h}` : h}:${minutes}`)
							}}
							onKeyDown={(e) => {
								if (e.key === 'Enter') {
									e.preventDefault()
									// @ts-ignore
									minutesRef?.current?.focus?.()
								}
							}}
						/>
						<span>:</span>
						<input
							min={0}
							max={59}
							type="number"
							ref={minutesRef}
							value={minutes}
							onChange={(e) => {
								const v = Number(e.target.value)
								const m = v > 23 ? 23 : v < 0 ? 0 : v
								onChange(`${hours}:${m < 10 ? `0${m}` : m}`)
							}}
							onKeyDown={(e) => {
								if (e.key === 'Enter') {
									e.preventDefault()
								}
							}}
						/>
					</div>
				)
			}}
		/>
	)
}
