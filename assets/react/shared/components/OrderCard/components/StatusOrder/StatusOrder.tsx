import { type FC, ReactNode, SetStateAction, useEffect, useState } from 'react'

import { OrderStatusEnum, OrderType, carrierUpdateStatusUrl } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { CircleChart } from '@ui/CircleChart/CircleChart'
import { Icon } from '@ui/Icon/Icon'
import { IconNameType } from '@ui/Icon/Icon.types'
import { cn } from '@utils/cn'

import styles from './StatusOrder.module.css'

// @ts-ignore
import awaitingPaymentGif from './images/awaitingPayment.gif'
// @ts-ignore
import awaitingPickupGif from './images/awaitingPickup.gif'
// @ts-ignore
import carrierMatchedGig from './images/carrierMatched.gif'
// @ts-ignore
import inTransitGif from './images/inTransit.gif'

interface StatusOrderProps {
	isCarrier?: boolean
	order: OrderType
	setModalId: (value: SetStateAction<any>) => void
	csrfToken?: string
}

const DELIVERY_LIMIT_MS = 48 * 60 * 60 * 1000

const useDeliveryCountdown = (paidDate: string | undefined, deliveredDate: string | undefined) => {
	const getRemaining = () => {
		if (!paidDate) return 0
		const deadline = new Date(paidDate).getTime() + DELIVERY_LIMIT_MS
		const referenceTime = deliveredDate ? new Date(deliveredDate).getTime() : Date.now()
		return Math.max(0, deadline - referenceTime)
	}

	const [remainingMs, setRemainingMs] = useState(getRemaining)

	useEffect(() => {
		if (!paidDate || deliveredDate) return
		const interval = setInterval(() => setRemainingMs(getRemaining()), 1000)
		return () => clearInterval(interval)
	}, [paidDate, deliveredDate])

	const isExpired = remainingMs === 0
	const percent = paidDate ? (remainingMs / DELIVERY_LIMIT_MS) * 100 : 0

	const hours = Math.floor(remainingMs / 3_600_000)
	const minutes = Math.floor((remainingMs % 3_600_000) / 60_000)
	const seconds = Math.floor((remainingMs % 60_000) / 1_000)

	const pad = (n: number) => String(n).padStart(2, '0')
	const timeLabel = isExpired ? '00:00:00' : `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
	const subtitle = deliveredDate ? 'remaining' : isExpired ? "Time's up" : 'left'

	return { percent, timeLabel, subtitle }
}

export const StatusOrder: FC<StatusOrderProps> = ({ isCarrier, order, setModalId, csrfToken }) => {
	const [valueForm, setValueForm] = useState<'PICKUP_DONE' | 'DELIVERED'>()
	const countdown = useDeliveryCountdown(order.paid_date, order.delivered_date)

	return (
		<div
			className={cn(styles.status, {
				[styles.sender]: !isCarrier,
				[styles.carrier]: isCarrier,
			})}
		>
			<div className={styles.title}>Status</div>

			{!isCarrier && (
				<div className={styles.statusWrapper}>
					<div className={styles.item}>
						Awaiting Payment
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.ACCEPTED && (
							<div className={styles.active}>
								{order.status <= OrderStatusEnum.INVOICED
									? 'Awaiting Payment'
									: 'Payment successful'}
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.ACCEPTED,
									})}
								>
									{order.status > OrderStatusEnum.INVOICED ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img
											alt=""
											src={awaitingPaymentGif}
											style={{ width: '48px', height: '48px' }}
										/>
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						Matching carrier
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.PAID && (
							<div className={styles.active}>
								{order.status < OrderStatusEnum.ASSIGNED ? 'Matching carrier' : 'Carrier matched'}
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.PAID,
									})}
								>
									{order.status > OrderStatusEnum.PAID ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img alt="" src={carrierMatchedGig} style={{ width: '48px', height: '48px' }} />
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						Awaiting pickup
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.ASSIGNED && (
							<div className={styles.active}>
								Awaiting pickup
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.AWAITING_PICKUP,
									})}
								>
									{order.status > OrderStatusEnum.AWAITING_PICKUP ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img alt="" src={awaitingPickupGif} style={{ width: '48px', height: '48px' }} />
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						In transit
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.PICKUP_DONE && (
							<div className={styles.active}>
								In transit
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.IN_TRANSIT,
									})}
								>
									{order.status > OrderStatusEnum.IN_TRANSIT ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img alt="" src={inTransitGif} style={{ width: '48px', height: '48px' }} />
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						Delivery
						<Icon type="mark_map" size={24} className="!translate-x-0.5" />
						{order.status >= OrderStatusEnum.DELIVERED && (
							<div className={styles.active}>
								{order.status === OrderStatusEnum.APPROVED ? 'Approved' : 'Delivered'}
								<div className={cn(styles.icon, styles.activeIcon)}>
									<Icon type="check_circle_1" size={20} />
								</div>
							</div>
						)}
					</div>
				</div>
			)}

			{isCarrier && (
				<div className={styles.statusWrapper}>
					<div className={styles.top}>
						<CircleChart
							size={150}
							percent={countdown.percent}
							title={countdown.timeLabel}
							subtitle={countdown.subtitle}
							countdown
						/>
					</div>

					<div className={styles.bottom}>
						<ItemCarrier
							iconType="up_box"
							label={<>Pickup done</>}
							checked={order.status >= OrderStatusEnum.IN_TRANSIT || valueForm === 'PICKUP_DONE'}
							isActive={order.status >= OrderStatusEnum.IN_TRANSIT}
							isWaiting={order.status === OrderStatusEnum.AWAITING_PICKUP}
							showInfo={order.status === OrderStatusEnum.AWAITING_PICKUP}
							infoText="Awaiting pickup"
							onClick={() => setValueForm((v) => (v === 'PICKUP_DONE' ? undefined : 'PICKUP_DONE'))}
						/>

						<div
							className={cn(styles.line, {
								[styles.big]: order.status >= OrderStatusEnum.IN_TRANSIT,
							})}
						/>

						<div className={styles.transitGroup}>
							<ItemCarrier
								iconType="vehicle_right"
								label={<>In transit</>}
								checked={order.status >= OrderStatusEnum.IN_TRANSIT}
								isActive={order.status >= OrderStatusEnum.IN_TRANSIT}
								isWaiting={false}
								showInfo={false}
							/>

							{order.status >= OrderStatusEnum.IN_TRANSIT && (
								<div
									className={cn(styles.nestedDelivered, {
										[styles.nestedDeliveredClickable]:
											order.status === OrderStatusEnum.IN_TRANSIT,
									})}
									onClick={
										order.status === OrderStatusEnum.IN_TRANSIT
											? () => setValueForm((v) => (v === 'DELIVERED' ? undefined : 'DELIVERED'))
											: undefined
									}
									onKeyDown={
										order.status === OrderStatusEnum.IN_TRANSIT
											? (e) => {
													if (e.key === 'Enter' || e.key === ' ') {
														e.preventDefault()
														setValueForm((v) => (v === 'DELIVERED' ? undefined : 'DELIVERED'))
													}
												}
											: undefined
									}
									role={order.status === OrderStatusEnum.IN_TRANSIT ? 'button' : undefined}
									tabIndex={order.status === OrderStatusEnum.IN_TRANSIT ? 0 : undefined}
								>
									<Checkbox
										className="pointer-events-none"
										value={
											order.status >= OrderStatusEnum.DELIVERED || valueForm === 'DELIVERED'
										}
										disabledWithoutCss
										disabled={order.status !== OrderStatusEnum.IN_TRANSIT}
									/>
									<span className="font-medium">Delivered</span>
									<Icon type="mark_map" size={22} className="ml-auto shrink-0 text-black/40" />
								</div>
							)}
						</div>

						<div
							className={cn(styles.line, {
								[styles.big]: order.status >= OrderStatusEnum.DELIVERED,
							})}
						/>

						<ItemCarrier
							iconType="check_circle_1"
							label={<>Approved</>}
							hideCheckbox
							isActive={order.status >= OrderStatusEnum.APPROVED}
							isWaiting={false}
							showInfo={order.status === OrderStatusEnum.DELIVERED}
							infoText="Pending admin approval"
						/>
					</div>
				</div>
			)}

			{!isCarrier && (
				<>
					<div>
						{/* {order.status < OrderStatusEnum.PICKUP_DONE && (
						<Button
						type="button"
						className="!w-full"
						variant="transparent"
						onClick={() => setModalId('cancel')}
						>
							Cancel order
						</Button>
					)} */}
					</div>

					{(order.status === OrderStatusEnum.PICKUP_DONE ||
						order.status === OrderStatusEnum.IN_TRANSIT ||
						order.status >= OrderStatusEnum.DELIVERED) && <div />}

					{/* {order.status >= OrderStatusEnum.DELIVERED && (
						<Button type="button" className="!w-full" onClick={() => setModalId('rate')}>
							Rate delivery
						</Button>
					)} */}
				</>
			)}

			{isCarrier && (
				<>
					<div className={styles.footer}>
						{!!valueForm && (
							<form
								method="POST"
								action={carrierUpdateStatusUrl(order.id)}
								className={styles.button}
								onSubmit={(e) => {
									if (
										valueForm === 'DELIVERED' &&
										!window.confirm(
											'Mark this order as delivered? The customer will be notified.',
										)
									) {
										e.preventDefault()
									}
								}}
							>
								<input type="hidden" name="_token" value={csrfToken} />
								<Button
									type="submit"
									variant="outline"
									name="action"
									value={valueForm}
									className="w-full"
								>
									Update status
								</Button>
							</form>
						)}

						{order.status < OrderStatusEnum.IN_TRANSIT && (
							<Button
								type="button"
								// className="!w-full"
								variant="transparent"
								onClick={() => setModalId('cancel')}
								className={styles.button}
							>
								Cancel order
							</Button>
						)}

						{/* {order.status === OrderStatusEnum.DELIVERED && (
							<Button
								type="button"
								className={styles.button}
								// className="!w-full"
								onClick={() => setModalId('rate')}
							>
								Rate delivery
							</Button>
						)} */}
					</div>
				</>
			)}
		</div>
	)
}

//
//
//
//
//

type ItemCarrierProps = {
	showInfo?: boolean
	isWaiting?: boolean
	isActive?: boolean
	checked?: boolean
	infoText?: string
	iconType: IconNameType
	label: ReactNode
	hideCheckbox?: boolean
	onClick?: () => void
}

const ItemCarrier: FC<ItemCarrierProps> = ({
	showInfo,
	isWaiting,
	isActive,
	iconType,
	label,
	checked,
	infoText,
	hideCheckbox,
	onClick,
}) => {
	return (
		<div
			className={cn(styles.itemWrapper, {
				['!cursor-pointer']: isWaiting && onClick,
			})}
			onClick={isWaiting ? onClick : undefined}
		>
			{showInfo && (
				<div className={styles.info}>
					<div className={styles.dot} />
					{infoText}
				</div>
			)}

			<div
				className={cn(styles.item, {
					[styles.waiting]: isWaiting,
					[styles.active]: isActive,
				})}
			>
				{hideCheckbox && <div />}
				{!hideCheckbox && (
					<Checkbox value={checked} disabledWithoutCss disabled={!isWaiting && !isActive} />
				)}

				<span>{label}</span>

				<div className={styles.icon}>
					<Icon type={iconType} size={25} />
				</div>
			</div>
		</div>
	)
}
