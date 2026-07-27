import { type FC, useEffect, useMemo, useState } from 'react'
import { Control, Controller, UseFormSetValue, UseFormWatch } from 'react-hook-form'

import {
	PICKUP_TIME_FROM_DEFAULT,
	PICKUP_TIME_SLOTS,
	PICKUP_TIME_TO_DEFAULT,
	PickupTypeEnum,
	months,
	years,
} from '@config/constants'
import { Calendar } from '@ui/Calendar/Calendar'
import { Icon } from '@ui/Icon/Icon'
import { Select } from '@ui/Select/Select'
import { Tabs } from '@ui/Tabs/Tabs'
import { cn } from '@utils/cn'

// @ts-ignore
import pickup_ready from './images/pickup_ready.png'

import styles from './ModalContent.module.css'

import { FormValues } from './types'

interface WhenContentProps {
	control: Control<FormValues, any, FormValues>
	watch: UseFormWatch<FormValues>
	setValue: UseFormSetValue<FormValues>
}

const slotOptions = PICKUP_TIME_SLOTS.map((slot) => ({ label: slot, value: slot }))

const compareTime = (a: string, b: string): number => a.localeCompare(b)

export const WhenContent: FC<WhenContentProps> = ({ control, watch, setValue }) => {
	const pickupType = watch('pickupType') || 'pickup_ready'
	const pickupTimeFrom = watch('pickupTimeFrom') || PICKUP_TIME_FROM_DEFAULT
	const pickupTimeTo = watch('pickupTimeTo') || PICKUP_TIME_TO_DEFAULT

	// Ensure RHF always has the dropdown defaults (09:00 / 17:00) when entering When.
	useEffect(() => {
		if (!watch('pickupTimeFrom')) {
			setValue('pickupTimeFrom', PICKUP_TIME_FROM_DEFAULT)
		}
		if (!watch('pickupTimeTo')) {
			setValue('pickupTimeTo', PICKUP_TIME_TO_DEFAULT)
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [])

	const createMonthDate = () => {
		const d = new Date()
		d.setDate(1)
		return d
	}

	const [calendarMonth, setCalendarMonth] = useState<Date>(createMonthDate())

	const fromOptions = useMemo(
		() =>
			slotOptions.map((opt) => ({
				...opt,
				disabled: compareTime(opt.value, pickupTimeTo) >= 0,
			})),
		[pickupTimeTo],
	)

	const toOptions = useMemo(
		() =>
			slotOptions.map((opt) => ({
				...opt,
				disabled: compareTime(opt.value, pickupTimeFrom) <= 0,
			})),
		[pickupTimeFrom],
	)

	const onSelectMonth = (monthIndex: number) => {
		setCalendarMonth((prev) => {
			const next = new Date(prev)
			next.setMonth(monthIndex)
			next.setDate(1)

			return next
		})
	}

	const handleYearChange = (year: number) => {
		setCalendarMonth((prev) => {
			const next = new Date(prev)
			next.setFullYear(year)
			next.setDate(1)

			return next
		})
	}

	const onFromChange = (value: string) => {
		setValue('pickupTimeFrom', value, { shouldDirty: true })
		if (compareTime(value, pickupTimeTo) >= 0) {
			const nextTo =
				PICKUP_TIME_SLOTS.find((slot) => compareTime(slot, value) > 0) || PICKUP_TIME_TO_DEFAULT
			setValue('pickupTimeTo', nextTo, { shouldDirty: true })
		}
	}

	const onToChange = (value: string) => {
		setValue('pickupTimeTo', value, { shouldDirty: true })
		if (compareTime(value, pickupTimeFrom) <= 0) {
			const reversed = [...PICKUP_TIME_SLOTS].reverse()
			const nextFrom =
				reversed.find((slot) => compareTime(slot, value) < 0) || PICKUP_TIME_FROM_DEFAULT
			setValue('pickupTimeFrom', nextFrom, { shouldDirty: true })
		}
	}

	return (
		<div className={cn(styles.body, styles.whenActive)}>
			<div className={styles.left}>
				<div className={styles.leftBlock}>
					<div className={styles.title}>Pickup date</div>

					<Controller
						control={control}
						name="pickupType"
						render={({ field: { value, onChange } }) => (
							<Tabs
								defaultValue={value}
								classNames={{ tab: styles.tab }}
								onChange={(v) => {
									onChange(v)
								}}
								items={[
									{
										label: (
											<div className={styles.label}>
												{value === 'pickup_ready' && (
													<div className={styles.icon}>
														<Icon type="box" size={20} />
													</div>
												)}
												{PickupTypeEnum['pickup_ready']}
											</div>
										),
										value: 'pickup_ready',
									},
									{
										label: (
											<div className={styles.label}>
												{value === 'pickup_later' && (
													<div className={styles.icon}>
														<Icon type="box" size={20} />
													</div>
												)}
												{PickupTypeEnum['pickup_later']}
											</div>
										),
										value: 'pickup_later',
									},
								]}
							/>
						)}
					/>
				</div>

				<div className={styles.leftBlock}>
					<div className={styles.title}>Pickup time (working days)</div>

					<div className={styles.timeRange}>
						<div className={styles.timeField}>
							<span className={styles.timeFieldPrefix}>from</span>
							<Select
								color="gray"
								value={pickupTimeFrom}
								defaultValue={PICKUP_TIME_FROM_DEFAULT}
								onChange={onFromChange}
								values={fromOptions}
							/>
						</div>

						<div className={styles.timeField}>
							<span className={styles.timeFieldPrefix}>to</span>
							<Select
								color="gray"
								value={pickupTimeTo}
								defaultValue={PICKUP_TIME_TO_DEFAULT}
								onChange={onToChange}
								values={toOptions}
							/>
						</div>
					</div>
				</div>
			</div>

			<div className={styles.right}>
				{pickupType === 'pickup_ready' && (
					<div className={styles.pickupReady}>
						<img src={pickup_ready} alt="" width={345} height={320} />

						<div className={styles.icon}>
							<Icon type="check_circle_2" size={26} />
						</div>
					</div>
				)}

				{pickupType === 'pickup_later' && (
					<div className={styles.pickupLater}>
						<div className={styles.top} key={pickupType}>
							<Controller
								control={control}
								name="pickupMonth"
								render={({ field: { value, onChange } }) => (
									<Select
										color="green"
										defaultValue={value?.toString()}
										value={value?.toString() || ''}
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
								name="pickupYear"
								render={({ field: { value, onChange } }) => (
									<Select
										color="green"
										defaultValue={value}
										value={value || ''}
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
							<Controller
								control={control}
								name="pickupDate"
								render={({ field: { value, onChange } }) => (
									<Calendar
										mode="single"
										month={calendarMonth}
										key={pickupType}
										setMonth={() => {}}
										selected={value}
										disableDaysAhead={1}
										onSelect={(v) => onChange(v)}
										className="rounded-lg border"
									/>
								)}
							/>
						</div>
					</div>
				)}
			</div>
		</div>
	)
}
