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

/** Figma Delivery pin wrap: 40×40, pad 8 → icon 24×24. */
const DeliveryPinIcon: FC<{ className?: string }> = ({ className }) => (
	<svg
		className={className}
		xmlns="http://www.w3.org/2000/svg"
		width={24}
		height={24}
		viewBox="3 0 16 22"
		fill="none"
		aria-hidden
	>
		<path
			d="M10.9988 0C6.60548 0 3.03125 3.57423 3.03125 7.96752C3.03125 13.4197 10.1614 21.4239 10.465 21.762C10.7502 22.0796 11.248 22.079 11.5326 21.762C11.8362 21.4239 18.9664 13.4197 18.9664 7.96752C18.9663 3.57423 15.3921 0 10.9988 0ZM10.9988 20.1837C8.59914 17.3332 4.46606 11.7095 4.46606 7.96761C4.46606 4.36537 7.39662 1.43481 10.9988 1.43481C14.601 1.43481 17.5316 4.36537 17.5316 7.96757C17.5315 11.7096 13.3991 17.3323 10.9988 20.1837Z"
			fill="currentColor"
		/>
		<path
			d="M11.0008 3.95898C8.79043 3.95898 6.99219 5.75727 6.99219 7.96767C6.99219 10.1781 8.79047 11.9764 11.0008 11.9764C13.2112 11.9764 15.0094 10.1781 15.0094 7.96767C15.0094 5.75727 13.2112 3.95898 11.0008 3.95898ZM11.0008 10.5415C9.58157 10.5415 8.427 9.38693 8.427 7.96767C8.427 6.54841 9.58161 5.3938 11.0008 5.3938C12.42 5.3938 13.5747 6.54841 13.5747 7.96767C13.5747 9.38693 12.42 10.5415 11.0008 10.5415Z"
			fill="currentColor"
		/>
	</svg>
)

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

/** Sender Delivery ETA lines (Figma Descriptions-med ~91×30, two lines). */
type SenderEtaLines = { primary: string; secondary?: string }

const formatSenderEtaFromDeadline = (iso: string): SenderEtaLines | null => {
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) return null
	const pad = (n: number) => String(n).padStart(2, '0')
	return {
		primary: `ETA: ${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()},`,
		secondary: `${pad(date.getHours())}:${pad(date.getMinutes())}`,
	}
}

/** Fallback when deadline is not resolved yet — order delivery window. */
const formatSenderEtaFromWindow = (order: OrderType): SenderEtaLines | null => {
	const dateLabel = order.delivery_date?.trim()
	if (!dateLabel) return null
	const from = order.delivery_time_from?.trim()
	const to = order.delivery_time_to?.trim()
	if (from && to && from !== to) {
		return { primary: `ETA: ${dateLabel},`, secondary: `${from} - ${to}` }
	}
	if (from) return { primary: `ETA: ${dateLabel}`, secondary: from }
	return { primary: `ETA: ${dateLabel}` }
}

/** Manual Change ETA value: `dd/mm/yyyy, hh:mm`. */
const formatEtaManualValue = (iso?: string): string => {
	const date = iso ? new Date(iso) : new Date(Date.now() + 48 * 60 * 60 * 1000)
	if (Number.isNaN(date.getTime())) return ''
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}, ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Digits-only mask → `dd/mm/yyyy, hh:mm` while typing. */
const formatEtaAsYouType = (raw: string): string => {
	const digits = raw.replace(/\D/g, '').slice(0, 12)
	let out = ''
	if (digits.length > 0) out = digits.slice(0, 2)
	if (digits.length >= 3) out += `/${digits.slice(2, 4)}`
	if (digits.length >= 5) out += `/${digits.slice(4, 8)}`
	if (digits.length >= 9) out += `, ${digits.slice(8, 10)}`
	if (digits.length >= 11) out += `:${digits.slice(10, 12)}`
	return out
}

const isValidEtaManualValue = (value: string): boolean => {
	const match = value.trim().match(/^(\d{2})\/(\d{2})\/(\d{4}),\s*(\d{2}):(\d{2})$/)
	if (!match) return false
	const day = Number(match[1])
	const month = Number(match[2])
	const year = Number(match[3])
	const hour = Number(match[4])
	const minute = Number(match[5])
	if (month < 1 || month > 12 || hour > 23 || minute > 59) return false
	const date = new Date(year, month - 1, day, hour, minute)
	return (
		date.getFullYear() === year &&
		date.getMonth() === month - 1 &&
		date.getDate() === day &&
		date.getHours() === hour &&
		date.getMinutes() === minute
	)
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
	const [etaInput, setEtaInput] = useState(() => formatEtaManualValue(order.deadline_at))
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
		if (order.deadline_at) return formatSenderEtaFromDeadline(order.deadline_at)
		return formatSenderEtaFromWindow(order)
	}, [order.deadline_at, order.delivery_date, order.delivery_time_from, order.delivery_time_to])

	const etaBaseline = useMemo(
		() => formatEtaManualValue(order.deadline_at),
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
					<div className={styles.timeline} aria-hidden />

					{/* Payment */}
					<div
						className={cn(styles.step, order.status >= OrderStatusEnum.ACCEPTED && styles.stepCard)}
					>
						<span className={styles.stepLabel}>
							{order.status >= OrderStatusEnum.ACCEPTED
								? order.status <= OrderStatusEnum.INVOICED
									? 'Awaiting Payment'
									: 'Payment successful'
								: 'Awaiting Payment'}
						</span>
						<div className={styles.stepIcon}>
							{order.status > OrderStatusEnum.INVOICED ? (
								<span className={styles.stepIconDone}>
									<Icon type="check_circle_1" size={20} />
								</span>
							) : order.status >= OrderStatusEnum.ACCEPTED ? (
								<img alt="" src={awaitingPaymentGif} className={styles.stepGif} />
							) : (
								<span className={styles.stepDot} />
							)}
						</div>
					</div>

					{/* Carrier matched */}
					<div
						className={cn(styles.step, order.status >= OrderStatusEnum.PAID && styles.stepCard)}
					>
						<span className={styles.stepLabel}>
							{order.status >= OrderStatusEnum.PAID
								? order.status < OrderStatusEnum.ASSIGNED
									? 'Matching carrier'
									: 'Carrier matched'
								: 'Matching carrier'}
						</span>
						<div className={styles.stepIcon}>
							{order.status > OrderStatusEnum.PAID ? (
								<span className={styles.stepIconDone}>
									<Icon type="check_circle_1" size={20} />
								</span>
							) : order.status >= OrderStatusEnum.PAID ? (
								<img alt="" src={carrierMatchedGig} className={styles.stepGif} />
							) : (
								<span className={styles.stepDot} />
							)}
						</div>
					</div>

					{/* Awaiting pickup */}
					<div
						className={cn(
							styles.step,
							order.status >= OrderStatusEnum.ASSIGNED && styles.stepCard,
						)}
					>
						<span className={styles.stepLabel}>Awaiting pickup</span>
						<div className={styles.stepIcon}>
							{order.status > OrderStatusEnum.AWAITING_PICKUP ? (
								<span className={styles.stepIconDone}>
									<Icon type="check_circle_1" size={20} />
								</span>
							) : order.status >= OrderStatusEnum.ASSIGNED ? (
								<img alt="" src={awaitingPickupGif} className={styles.stepGif} />
							) : (
								<span className={styles.stepDot} />
							)}
						</div>
					</div>

					{/* In transit */}
					<div
						className={cn(
							styles.step,
							order.status >= OrderStatusEnum.PICKUP_DONE && styles.stepCard,
						)}
					>
						<span className={styles.stepLabel}>In transit</span>
						<div className={styles.stepIcon}>
							{order.status > OrderStatusEnum.IN_TRANSIT ? (
								<span className={styles.stepIconDone}>
									<Icon type="check_circle_1" size={20} />
								</span>
							) : order.status >= OrderStatusEnum.PICKUP_DONE ? (
								<img alt="" src={inTransitGif} className={styles.stepGif} />
							) : (
								<span className={styles.stepDot} />
							)}
						</div>
					</div>

					{/* Delivery */}
					<div
						className={cn(
							styles.step,
							styles.stepDelivery,
							order.status >= OrderStatusEnum.DELIVERED && styles.stepCard,
						)}
					>
						<div className={styles.deliveryCopy}>
							<span className={styles.deliveryLabel}>
								{order.status >= OrderStatusEnum.DELIVERED
									? order.status === OrderStatusEnum.APPROVED
										? 'Approved'
										: 'Delivered'
									: 'Delivery'}
							</span>
							{senderDeliveryEta && order.status < OrderStatusEnum.DELIVERED && (
								<span className={styles.deliveryEta}>
									<span>{senderDeliveryEta.primary}</span>
									{senderDeliveryEta.secondary && (
										<span>{senderDeliveryEta.secondary}</span>
									)}
								</span>
							)}
						</div>
						<div className={styles.stepIcon}>
							{order.status >= OrderStatusEnum.DELIVERED ? (
								<span className={styles.stepIconDone}>
									<Icon type="check_circle_1" size={20} />
								</span>
							) : (
								<span className={styles.deliveryPinWrap}>
									<DeliveryPinIcon className={styles.deliveryPin} />
								</span>
							)}
						</div>
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
												onSubmit={(e) => {
													if (!isValidEtaManualValue(etaInput)) {
														e.preventDefault()
														e.currentTarget.reportValidity()
													}
												}}
											>
												<input type="hidden" name="_token" value={changeEtaCsrfToken} />
												{/* Figma Frame 1932: manual text in format dd/mm/yyyy, hh:mm */}
												<div ref={etaPillRef} className={styles.carrierChangeEtaPill}>
													<input
														type="text"
														name="eta"
														required
														inputMode="numeric"
														autoComplete="off"
														spellCheck={false}
														placeholder="dd/mm/yyyy, hh:mm"
														pattern="\d{2}/\d{2}/\d{4},\s*\d{2}:\d{2}"
														title="Use format dd/mm/yyyy, hh:mm"
														value={etaInput}
														onChange={(e) => setEtaInput(formatEtaAsYouType(e.target.value))}
														className={styles.carrierChangeEtaInput}
														aria-label="ETA (time of arrival)"
													/>
												</div>
												<button
													ref={etaUpdateBtnRef}
													type="submit"
													className={styles.carrierUpdateEtaBtn}
													disabled={!isValidEtaManualValue(etaInput)}
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
