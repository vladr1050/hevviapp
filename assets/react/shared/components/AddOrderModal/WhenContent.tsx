import { type FC, useState } from 'react'
import { Control, Controller, UseFormWatch } from 'react-hook-form'

import { PickupTypeEnum, months, years } from '@config/constants'
import { Calendar } from '@ui/Calendar/Calendar'
import { Icon } from '@ui/Icon/Icon'
import { RadioButtons } from '@ui/RadioButtons/RadioButtons'
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
}

export const WhenContent: FC<WhenContentProps> = ({ control, watch }) => {
	const [tabItem, setTabItem] = useState<'pickup_ready' | 'pickup_later'>(
		watch('pickupType') || 'pickup_ready'
	)

	const createMonthDate = () => {
		const d = new Date()
		d.setDate(1)
		return d
	}

	const [calendarMonth, setCalendarMonth] = useState<Date>(createMonthDate())

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
									// @ts-ignore
									setTabItem(v)
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
					<div className={styles.title}>Pickup time in working days</div>

					<RadioButtons
						control={control}
						name="pickupTime"
						defaultValue="anytime"
						items={[
							{ label: 'Any time', value: 'anytime' },
							{ label: 'Morning (8:00 – 13:00)', value: '8:00-13:00' },
							{ label: 'Afternoon (13:00 – 18:00)', value: '13:00-18:00' },
						]}
					/>
				</div>
			</div>

			<div className={styles.right}>
				{tabItem === 'pickup_ready' && (
					<div className={styles.pickupReady}>
						<img src={pickup_ready} alt="" width={345} height={320} />

						<div className={styles.icon}>
							<Icon type="check_circle_2" size={26} />
						</div>
					</div>
				)}

				{tabItem === 'pickup_later' && (
					<div className={styles.pickupLater}>
						<div className={styles.top} key={tabItem}>
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
										key={tabItem}
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
