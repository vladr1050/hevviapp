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

type CarrierFlatRowProps = {
	muted?: boolean
	done?: boolean
	clickable?: boolean
	checkbox?: boolean
	checked?: boolean
	onActivate?: () => void
	label: ReactNode
	iconType: IconNameType
	/** Softer truck / step icon (e.g. before in-transit) */
	iconPending?: boolean
}

const CarrierFlatRow: FC<CarrierFlatRowProps> = ({
	muted,
	done,
	clickable,
	checkbox = true,
	checked = false,
	onActivate,
	label,
	iconType,
	iconPending,
}) => (
	<div
		className={cn(
			styles.carrierFlatCard,
			muted && styles.carrierFlatCardMuted,
			clickable && styles.carrierFlatCardClickable,
		)}
		onClick={clickable ? onActivate : undefined}
		onKeyDown={
			clickable
				? (e) => {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault()
							onActivate?.()
						}
					}
				: undefined
		}
		role={clickable ? 'button' : undefined}
		tabIndex={clickable ? 0 : undefined}
	>
		{checkbox ? (
			<Checkbox
				className={cn(styles.carrierFlatCheckbox, 'pointer-events-none')}
				color={muted ? 'gray' : 'default'}
				value={checked}
				disabledWithoutCss
				disabled={!clickable}
			/>
		) : (
			<div className="w-5 shrink-0" aria-hidden />
		)}
		<span>{label}</span>
		<div
			className={cn(
				styles.carrierFlatIcon,
				done && styles.carrierFlatIconDone,
				iconPending && styles.carrierFlatIconPending,
			)}
		>
			<Icon type={iconType} size={22} />
		</div>
	</div>
)

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
	const deliveredToLabel = order.address?.to?.trim()
		? `Delivered to ${order.address.to.trim()}`
		: 'Delivered'

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
						{order.status === OrderStatusEnum.APPROVED ? (
							<div className={styles.carrierHeroApproved}>
								<Icon type="vehicle_check" size={54} />
							</div>
						) : (
							<CircleChart
								size={170}
								percent={countdown.percent}
								title={`${countdown.timeLabel}h`}
								titleClassName={styles.carrierCountdownTitle}
								subtitle={countdown.subtitle}
								countdown
							/>
						)}
					</div>

					<div className={styles.bottom}>
						{order.status === OrderStatusEnum.APPROVED ? (
							<>
								<CarrierFlatRow
									done
									checkbox
									checked
									label="Pickup done"
									iconType="up_box"
								/>
								<CarrierFlatRow
									done
									checkbox
									checked
									label={deliveredToLabel}
									iconType="vehicle_right"
								/>
								<CarrierFlatRow
									done
									checkbox={false}
									checked
									label="Approved by Sender"
									iconType="check_circle_1"
								/>
							</>
						) : order.status === OrderStatusEnum.DELIVERED ? (
							<>
								<CarrierFlatRow
									done
									checkbox
									checked
									label="Pickup done"
									iconType="up_box"
								/>
								<CarrierFlatRow
									done
									checkbox
									checked
									label={deliveredToLabel}
									iconType="vehicle_right"
								/>
								<div className={styles.carrierPhaseStack}>
									<div className={styles.carrierPhaseHeader}>
										<span className={styles.carrierPhaseHeaderContent}>
											<span className={styles.carrierPhaseHeaderDot} aria-hidden />
											<span className={styles.carrierPhaseHeaderLabel}>Pending approval</span>
										</span>
									</div>
									<CarrierFlatRow
										muted
										checkbox
										checked={false}
										label="Approved by Sender"
										iconType="check_circle_1"
									/>
								</div>
							</>
						) : order.status === OrderStatusEnum.PICKUP_DONE ||
						  order.status === OrderStatusEnum.IN_TRANSIT ? (
							<>
								<CarrierFlatRow
									done
									checkbox
									checked
									label="Pickup done"
									iconType="up_box"
								/>
								<div className={styles.carrierPhaseStack}>
									<div className={styles.carrierPhaseHeader}>
										<span className={styles.carrierPhaseHeaderContent}>
											<span className={styles.carrierPhaseHeaderDot} aria-hidden />
											<span className={styles.carrierPhaseHeaderLabel}>In transit</span>
										</span>
									</div>
									<CarrierFlatRow
										clickable={order.status === OrderStatusEnum.IN_TRANSIT}
										checkbox
										checked={
											order.status >= OrderStatusEnum.DELIVERED ||
											valueForm === 'DELIVERED'
										}
										onActivate={() =>
											setValueForm((v) => (v === 'DELIVERED' ? undefined : 'DELIVERED'))
										}
										label={deliveredToLabel}
										iconType="vehicle_right"
										done={order.status >= OrderStatusEnum.DELIVERED}
										iconPending={order.status === OrderStatusEnum.PICKUP_DONE}
									/>
								</div>
								<CarrierFlatRow
									muted
									checkbox
									checked={false}
									label="Approved by Sender"
									iconType="check_circle_1"
								/>
							</>
						) : (
							<>
								<div className={styles.carrierPhaseStack}>
									<div className={styles.carrierPhaseHeader}>
										<span className={styles.carrierPhaseHeaderContent}>
											<span className={styles.carrierPhaseHeaderDot} aria-hidden />
											<span className={styles.carrierPhaseHeaderLabel}>Awaiting pickup</span>
										</span>
									</div>
									<CarrierFlatRow
										clickable={order.status === OrderStatusEnum.AWAITING_PICKUP}
										checkbox
										checked={valueForm === 'PICKUP_DONE'}
										onActivate={() =>
											setValueForm((v) => (v === 'PICKUP_DONE' ? undefined : 'PICKUP_DONE'))
										}
										label="Pickup done"
										iconType="up_box"
										iconPending={valueForm !== 'PICKUP_DONE'}
									/>
								</div>
								<div className={styles.carrierAwaitingBelow}>
									<CarrierFlatRow
										muted
										checkbox
										checked={false}
										label={deliveredToLabel}
										iconType="vehicle_right"
									/>
									<CarrierFlatRow
										muted
										checkbox
										checked={false}
										label="Approved by Sender"
										iconType="check_circle_1"
									/>
								</div>
							</>
						)}
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
								variant="transparent"
								onClick={() => setModalId('cancel')}
								className={styles.footerCancel}
							>
								Cancel Order
							</Button>
						)}
					</div>
				</>
			)}
		</div>
	)
}

