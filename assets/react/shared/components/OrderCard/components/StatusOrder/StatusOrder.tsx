import { type FC, ReactNode, SetStateAction, useEffect, useMemo, useRef, useState } from 'react'

import {
	OrderStatusEnum,
	OrderType,
	carrierChangeEtaUrl,
	carrierUpdateStatusUrl,
} from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
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
	changeEtaCsrfToken?: string
}

type CountdownState =
	| { phase: 'pending'; opensAtLabel: string }
	| { phase: 'running'; timeLabel: string; subtitle: string }

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

const formatPickupOpensAt = (iso: string): string => {
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) return ''
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${pad(date.getDate())}.${pad(date.getMonth() + 1)} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Digital countdown: 48h → `2d 00:00`, 47h59m → `1d 23:59`. */
const formatDigitalCountdown = (remainingMs: number): string => {
	const totalMinutes = Math.max(0, Math.floor(remainingMs / 60_000))
	const days = Math.floor(totalMinutes / (24 * 60))
	const hours = Math.floor((totalMinutes % (24 * 60)) / 60)
	const minutes = totalMinutes % 60
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${days}d ${pad(hours)}:${pad(minutes)}`
}

const formatEtaDisplay = (iso: string): string => {
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) return ''
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}, ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Sender Delivery ETA pipe format (same instant as carrier): `ETA: 19/08/2024 | 14:00`. */
const formatSenderEtaPipe = (iso: string): string => {
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) return ''
	const pad = (n: number) => String(n).padStart(2, '0')
	return `ETA: ${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} | ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Fallback when deadline is not resolved yet — order delivery window. */
const formatSenderEtaWindow = (order: OrderType): string => {
	const dateLabel = order.delivery_date?.trim()
	if (!dateLabel) return ''
	const from = order.delivery_time_from?.trim()
	const to = order.delivery_time_to?.trim()
	if (from && to && from !== to) return `ETA: ${dateLabel}, ${from} - ${to}`
	if (from) return `ETA: ${dateLabel} | ${from}`
	return `ETA: ${dateLabel}`
}

const toDatetimeLocalValue = (iso?: string): string => {
	const date = iso ? new Date(iso) : new Date(Date.now() + 48 * 60 * 60 * 1000)
	if (Number.isNaN(date.getTime())) return ''
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Figma Change ETA pill: `27/07/2026 | 20:00` from datetime-local value. */
const formatEtaPipeFromLocal = (local: string): string => {
	const match = local.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/)
	if (!match) return ''
	return `${match[3]}/${match[2]}/${match[1]} | ${match[4]}:${match[5]}`
}

const useDeliveryCountdown = (
	pickupReadyAt: string | undefined,
	deadlineAt: string | undefined,
	deliveredDate: string | undefined,
): CountdownState | null => {
	const compute = (): CountdownState | null => {
		if (!deadlineAt || !pickupReadyAt) return null

		const anchorMs = new Date(pickupReadyAt).getTime()
		const deadlineMs = new Date(deadlineAt).getTime()
		if (Number.isNaN(anchorMs) || Number.isNaN(deadlineMs)) return null

		const referenceMs = deliveredDate ? new Date(deliveredDate).getTime() : Date.now()

		if (referenceMs < anchorMs) {
			return { phase: 'pending', opensAtLabel: formatPickupOpensAt(pickupReadyAt) }
		}

		const remainingMs = Math.max(0, deadlineMs - referenceMs)
		const isExpired = remainingMs === 0
		const timeLabel = formatDigitalCountdown(remainingMs)
		const subtitle = deliveredDate
			? 'until order completion'
			: isExpired
				? "Time's up"
				: 'until order completion'

		return { phase: 'running', timeLabel, subtitle }
	}

	const [state, setState] = useState<CountdownState | null>(compute)

	useEffect(() => {
		if (!deadlineAt || !pickupReadyAt || deliveredDate) {
			setState(compute())
			return
		}
		setState(compute())
		const interval = setInterval(() => setState(compute()), 1000)
		return () => clearInterval(interval)
	}, [pickupReadyAt, deadlineAt, deliveredDate])

	return state
}

export const StatusOrder: FC<StatusOrderProps> = ({
	isCarrier,
	order,
	setModalId,
	csrfToken,
	changeEtaCsrfToken,
}) => {
	const [valueForm, setValueForm] = useState<'PICKUP_DONE' | 'DELIVERED'>()
	const [showChangeEta, setShowChangeEta] = useState(false)
	const [etaInput, setEtaInput] = useState(() => toDatetimeLocalValue(order.deadline_at))
	const etaPillRef = useRef<HTMLDivElement>(null)
	const etaUpdateBtnRef = useRef<HTMLButtonElement>(null)

	const countdown = useDeliveryCountdown(
		order.pickup_ready_at,
		order.deadline_at,
		order.delivered_date,
	)
	const deliveredToLabel = order.address?.to?.trim()
		? `Delivered to ${order.address.to.trim()}`
		: 'Delivered'

	const canChangeEta =
		!!changeEtaCsrfToken &&
		order.status >= OrderStatusEnum.AWAITING_PICKUP &&
		order.status < OrderStatusEnum.DELIVERED &&
		!!order.deadline_at

	const etaLabel = useMemo(() => {
		if (order.deadline_at) return formatEtaDisplay(order.deadline_at)
		return ''
	}, [order.deadline_at])

	/** Same deadline source as carrier ETA; falls back to requested delivery window. */
	const senderDeliveryEta = useMemo(() => {
		if (order.deadline_at) return formatSenderEtaPipe(order.deadline_at)
		return formatSenderEtaWindow(order)
	}, [order.deadline_at, order.delivery_date, order.delivery_time_from, order.delivery_time_to])

	const etaBaseline = useMemo(
		() => toDatetimeLocalValue(order.deadline_at),
		[order.deadline_at],
	)

	useEffect(() => {
		setEtaInput(etaBaseline)
	}, [etaBaseline])

	useEffect(() => {
		if (!showChangeEta) return

		const onPointerDown = (event: PointerEvent) => {
			if (etaInput !== etaBaseline) return
			const target = event.target as Node | null
			if (!target) return
			if (etaPillRef.current?.contains(target)) return
			if (etaUpdateBtnRef.current?.contains(target)) return
			setEtaInput(etaBaseline)
			setShowChangeEta(false)
		}

		document.addEventListener('pointerdown', onPointerDown)
		return () => document.removeEventListener('pointerdown', onPointerDown)
	}, [showChangeEta, etaInput, etaBaseline])

	return (
		<div
			className={cn(styles.status, {
				[styles.sender]: !isCarrier,
				[styles.carrier]: isCarrier,
			})}
		>
			{!isCarrier && <div className={styles.title}>Status</div>}

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

					<div className={cn(styles.item, styles.deliveryItem)}>
						<div className={styles.deliveryCopy}>
							<span className={styles.deliveryLabel}>Delivery</span>
							{senderDeliveryEta && order.status < OrderStatusEnum.DELIVERED && (
								<span className={styles.deliveryEta}>{senderDeliveryEta}</span>
							)}
						</div>
						<Icon type="mark_map" size={24} className={styles.deliveryPin} />
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

			{!isCarrier &&
				order.status >= OrderStatusEnum.ACCEPTED &&
				order.status < OrderStatusEnum.PAID && (
					<div className={styles.senderFooter}>
						<Button
							type="button"
							variant="transparent"
							onClick={() => setModalId('cancel')}
							className={styles.senderCancel}
						>
							Cancel order
						</Button>
					</div>
				)}

			{isCarrier && (
				<div className={styles.statusWrapper}>
					<div className={styles.top}>
						{order.status === OrderStatusEnum.APPROVED ? (
							<div className={styles.carrierHeroApproved}>
								<Icon type="vehicle_check" size={54} />
							</div>
						) : countdown?.phase === 'pending' ? (
							<div className={styles.carrierPickupPending}>
								<Icon type="clock_1_light" size={36} />
								<span className={styles.carrierPickupPendingTitle}>Pickup opens at</span>
								<span className={styles.carrierPickupPendingValue}>
									{countdown.opensAtLabel}
								</span>
							</div>
						) : countdown?.phase === 'running' ? (
							<div className={styles.carrierCountdownBlock}>
								<div className={styles.carrierCountdownCard}>
									<div className={styles.carrierCountdownIcon}>
										<Icon type="stopwatch" size={20} currentColor />
									</div>
									<div className={styles.carrierCountdownText}>
										<div className={styles.carrierCountdownValue}>{countdown.timeLabel}</div>
										<div className={styles.carrierCountdownSubtitle}>{countdown.subtitle}</div>
									</div>
								</div>

								{etaLabel && (
									<div className={styles.carrierEtaBlock}>
										<div className={styles.carrierEtaLabel}>ETA (time of arrival)</div>

										{canChangeEta && showChangeEta ? (
											<form
												method="POST"
												action={carrierChangeEtaUrl(order.id)}
												className={styles.carrierChangeEtaForm}
											>
												<input type="hidden" name="_token" value={changeEtaCsrfToken} />
												{/* Figma Frame 1932: pill stays in the value slot; Update keeps Change ETA position */}
												<div ref={etaPillRef} className={styles.carrierChangeEtaPill}>
													<span className={styles.carrierChangeEtaPillText} aria-hidden>
														{formatEtaPipeFromLocal(etaInput)}
													</span>
													<input
														type="datetime-local"
														name="eta"
														required
														value={etaInput}
														onChange={(e) => setEtaInput(e.target.value)}
														className={styles.carrierChangeEtaNative}
														aria-label="ETA (time of arrival)"
													/>
												</div>
												<button
													ref={etaUpdateBtnRef}
													type="submit"
													className={styles.carrierUpdateEtaBtn}
												>
													Update
												</button>
											</form>
										) : (
											<>
												<div className={styles.carrierEtaValueSlot}>
													<div className={styles.carrierEtaValue}>{etaLabel}</div>
												</div>
												{canChangeEta && (
													<button
														type="button"
														className={styles.carrierChangeEtaBtn}
														onClick={() => setShowChangeEta(true)}
													>
														Change ETA
													</button>
												)}
											</>
										)}
									</div>
								)}
							</div>
						) : (
							<div className={styles.carrierPickupPending}>
								<Icon type="clock_1_light" size={36} />
								<span className={styles.carrierPickupPendingTitle}>Awaiting payment</span>
							</div>
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
