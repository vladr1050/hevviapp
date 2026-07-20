import { type FC, type PointerEvent, useCallback, useEffect, useRef, useState } from 'react'

import { EMAIL, PHONE, PUBLIC_SUPPORT_CONTACT_URL, Routes } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { CircleChart } from '@ui/CircleChart/CircleChart'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Info.module.css'

export interface OngoingOrderCard {
	id: string
	name: string
	status: string
	delivered?: boolean
	pickup_ready_at?: string | null
	deadline_at?: string | null
	delivered_date?: string | null
}

interface InfoProps {
	orders?: OngoingOrderCard[]
	isCarrier?: boolean
	device?: DeviceType
}

const HOVER_CLOSE_DELAY_MS = 150
const DELIVERY_LIMIT_MS = 48 * 60 * 60 * 1000
const SWIPE_THRESHOLD_PX = 40

type CountdownView = {
	hoursLabel: string
	percent: number
	subtitle: string
} | null

const useCardCountdown = (order: OngoingOrderCard | undefined): CountdownView => {
	const compute = (): CountdownView => {
		if (!order || order.delivered || !order.deadline_at || !order.pickup_ready_at) {
			return null
		}

		const anchorMs = new Date(order.pickup_ready_at).getTime()
		const deadlineMs = new Date(order.deadline_at).getTime()
		if (Number.isNaN(anchorMs) || Number.isNaN(deadlineMs)) {
			return null
		}

		const now = Date.now()
		if (now < anchorMs) {
			return { hoursLabel: '—', percent: 100, subtitle: 'soon' }
		}

		const remainingMs = Math.max(0, deadlineMs - now)
		const hours = Math.ceil(remainingMs / 3_600_000)
		const percent = Math.min(100, Math.max(0, (remainingMs / DELIVERY_LIMIT_MS) * 100))

		return {
			hoursLabel: remainingMs === 0 ? '0' : String(hours),
			percent,
			subtitle: remainingMs === 0 ? "Time's up" : 'left',
		}
	}

	const [state, setState] = useState<CountdownView>(compute)

	useEffect(() => {
		setState(compute())
		if (!order || order.delivered || !order.deadline_at) {
			return
		}
		const id = setInterval(() => setState(compute()), 30_000)
		return () => clearInterval(id)
	}, [order?.id, order?.deadline_at, order?.pickup_ready_at, order?.delivered])

	return state
}

const OrderCardContent: FC<{ order: OngoingOrderCard; isCarrier: boolean }> = ({
	order,
	isCarrier,
}) => {
	const countdown = useCardCountdown(order)
	const detailsHref = `${isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS}/${order.id}`

	return (
		<div className={styles.header}>
			<div
				className={cn(styles.leftBlock, {
					[styles.leftBlockDelivered]: !!order.delivered,
				})}
			>
				{order.delivered ? (
					<Icon type="green_check" size={72} className={styles.check} />
				) : countdown ? (
					<CircleChart
						size={88}
						percent={countdown.percent}
						title={`${countdown.hoursLabel}h`}
						subtitle={countdown.subtitle}
						strokeWidth={6}
						countdown
						className={styles.circleChart}
						titleClassName={styles.circleTitle}
						subtitleClassName={styles.circleSubtitle}
					/>
				) : (
					<div className={styles.circleFallback}>
						<span className={styles.circleFallbackDot} />
					</div>
				)}
			</div>

			<div className={styles.rightBlock}>
				<div className={styles.wrapper}>
					<span className={styles.cardTitle}>{order.name}</span>
					<span className={styles.cardSubtitle}>{order.status}</span>
				</div>

				<a href={detailsHref} className={styles.link}>
					View details
				</a>
			</div>
		</div>
	)
}

export const Info: FC<InfoProps> = (props) => {
	const { orders = [], isCarrier = false, device } = props
	const { isMobile } = useDevice(device)

	const [currentOrder, setCurrentOrder] = useState(0)
	const [ordersOpen, setOrdersOpen] = useState(false)
	const [supportOpen, setSupportOpen] = useState(false)
	const [supportPhone, setSupportPhone] = useState(PHONE)

	const supportContactLoadedRef = useRef(false)
	const supportCloseTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
	const ordersCloseTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
	const swipeStartXRef = useRef<number | null>(null)

	const orderCount = orders.length
	const safeIndex = orderCount === 0 ? 0 : Math.min(currentOrder, orderCount - 1)
	const activeOrder = orderCount > 0 ? orders[safeIndex] : undefined
	const hasPrev = orderCount > 1
	const hasNext = orderCount > 1

	const goPrev = useCallback(() => {
		setCurrentOrder((v) => (v <= 0 ? Math.max(orderCount - 1, 0) : v - 1))
	}, [orderCount])

	const goNext = useCallback(() => {
		setCurrentOrder((v) => (v >= orderCount - 1 ? 0 : v + 1))
	}, [orderCount])

	const loadSupportContact = useCallback(() => {
		if (supportContactLoadedRef.current) {
			return
		}
		void fetch(PUBLIC_SUPPORT_CONTACT_URL, { credentials: 'same-origin' })
			.then((res) => (res.ok ? res.json() : null))
			.then((data: { email?: string | null; phone?: string | null } | null) => {
				if (!data) {
					return
				}
				supportContactLoadedRef.current = true
				if (data.phone?.trim()) {
					setSupportPhone(data.phone.trim())
				}
			})
			.catch(() => {})
	}, [])

	const openSupport = useCallback(() => {
		if (supportCloseTimerRef.current) {
			clearTimeout(supportCloseTimerRef.current)
			supportCloseTimerRef.current = null
		}
		setSupportOpen(true)
		loadSupportContact()
	}, [loadSupportContact])

	const scheduleSupportClose = useCallback(() => {
		if (supportCloseTimerRef.current) {
			clearTimeout(supportCloseTimerRef.current)
		}
		supportCloseTimerRef.current = setTimeout(() => {
			setSupportOpen(false)
			supportCloseTimerRef.current = null
		}, HOVER_CLOSE_DELAY_MS)
	}, [])

	const openOrders = useCallback(() => {
		if (ordersCloseTimerRef.current) {
			clearTimeout(ordersCloseTimerRef.current)
			ordersCloseTimerRef.current = null
		}
		setOrdersOpen(true)
	}, [])

	const scheduleOrdersClose = useCallback(() => {
		if (ordersCloseTimerRef.current) {
			clearTimeout(ordersCloseTimerRef.current)
		}
		ordersCloseTimerRef.current = setTimeout(() => {
			setOrdersOpen(false)
			ordersCloseTimerRef.current = null
		}, HOVER_CLOSE_DELAY_MS)
	}, [])

	const onPointerDown = (e: PointerEvent) => {
		swipeStartXRef.current = e.clientX
	}

	const onPointerUp = (e: PointerEvent) => {
		if (swipeStartXRef.current === null || orderCount < 2) {
			swipeStartXRef.current = null
			return
		}
		const delta = e.clientX - swipeStartXRef.current
		swipeStartXRef.current = null
		if (delta <= -SWIPE_THRESHOLD_PX) {
			goNext()
		} else if (delta >= SWIPE_THRESHOLD_PX) {
			goPrev()
		}
	}

	useEffect(() => {
		if (currentOrder >= orderCount && orderCount > 0) {
			setCurrentOrder(orderCount - 1)
		}
	}, [currentOrder, orderCount])

	if (isMobile) return null

	const telHref = supportPhone.replace(/\s/g, '')
	const supportHoverHandlers = {
		onMouseEnter: openSupport,
		onMouseLeave: scheduleSupportClose,
	}
	const ordersHoverHandlers = {
		onMouseEnter: openOrders,
		onMouseLeave: scheduleOrdersClose,
	}

	return (
		<>
			{orderCount > 0 && (
				<div className={styles.ordersRoot} {...ordersHoverHandlers}>
					{!ordersOpen && (
						<button type="button" className={styles.orders} aria-label="Ongoing orders">
							<span aria-hidden />
							{orderCount} ongoing order{orderCount === 1 ? '' : 's'}
						</button>
					)}

					{ordersOpen && activeOrder && (
						<div className={styles.ordersPopover}>
							{hasPrev && (
								<button
									type="button"
									className={cn(styles.sidePeek, styles.leftPeek)}
									aria-label="Previous order"
									onClick={goPrev}
								/>
							)}
							{hasNext && (
								<button
									type="button"
									className={cn(styles.sidePeek, styles.rightPeek)}
									aria-label="Next order"
									onClick={goNext}
								/>
							)}

							<div
								className={styles.content}
								onPointerDown={onPointerDown}
								onPointerUp={onPointerUp}
							>
								<OrderCardContent
									key={activeOrder.id}
									order={activeOrder}
									isCarrier={isCarrier}
								/>

								{orderCount > 1 && (
									<div className={styles.footer}>
										{orders.map((order, index) => (
											<button
												type="button"
												key={order.id}
												className={cn(styles.dot, {
													[styles.dotActive]: index === safeIndex,
												})}
												aria-label={`Show ${order.name}`}
												onClick={() => setCurrentOrder(index)}
											/>
										))}
									</div>
								)}
							</div>
						</div>
					)}
				</div>
			)}

			<div
				className={cn(styles.supportRoot, { [styles.supportRootOpen]: supportOpen })}
				{...supportHoverHandlers}
			>
				{supportOpen && (
					<div className={styles.infoPopover}>
						<div className={styles.avatar} aria-hidden />

						<div className={styles.body}>
							<h3 className={styles.title}>Need help?</h3>

							<div className={styles.contacts}>
								<a className={styles.contact} href={`tel:${telHref}`}>
									{supportPhone}
								</a>
								<a className={styles.contact} href={`mailto:${EMAIL}`}>
									{EMAIL}
								</a>
							</div>
						</div>

						<div className={cn(styles.infoIcon, styles.infoIconInPopover)}>?</div>
					</div>
				)}

				{!supportOpen && <div className={styles.infoIcon}>?</div>}
			</div>
		</>
	)
}
