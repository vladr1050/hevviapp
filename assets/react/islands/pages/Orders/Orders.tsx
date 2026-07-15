import type { FC } from 'react'

import { EMPTY_STRING, OrderStatusEnum, OrderType, Routes } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import { OrdersEmptyState } from './components/OrdersEmptyState'
import { OrdersFilters } from './components/OrdersFilters'
import { OrdersPagination } from './components/OrdersPagination'
import type { OrdersFiltersState } from './ordersQuery'
import styles from './Orders.module.css'

import { MobilePage } from '../MobilePage/MobilePage'

interface OrdersPaginationProps {
	page: number
	perPage: number
	total: number
	totalPages: number
}

interface OrdersPageProps {
	title: string
	isCarrier?: boolean
	orders?: OrderType[]
	pagination?: OrdersPaginationProps
	filters?: OrdersFiltersState
	device?: DeviceType
}

const formatRoute = (order: OrderType): string => {
	const from = order.address?.from?.trim()
	const to = order.address?.to?.trim()

	if (from && to) {
		return `${from} → ${to}`
	}

	return to || from || EMPTY_STRING
}

export const OrdersPage: FC<OrdersPageProps> = (props) => {
	const { title, orders, isCarrier, pagination, filters, device } = props
	const orderCount = pagination?.total ?? orders?.length ?? 0
	const isEmpty = orderCount === 0
	const ordersBaseUrl = isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS

	const { isMobile } = useDevice(device)

	if (isMobile) return <MobilePage />

	return (
		<div className={styles.page}>
			<div className={styles.pageHeader}>
				<h1 className={styles.title}>
					{title} <span>({orderCount})</span>
				</h1>

				{filters && (
					<OrdersFilters baseUrl={ordersBaseUrl} filters={filters} />
				)}
			</div>

			{isEmpty ? (
				<div className={styles.emptyWrapper}>
					<OrdersEmptyState isCarrier={isCarrier} />
				</div>
			) : (
				<div className={styles.tableWrapper}>
					<div className={styles.header}>
						<span>Order №</span>
						<span>Comment</span>
						<span>Item</span>
						<span>Address</span>
						<span>Pickup Date</span>
						<span>Type</span>
						<span>Price</span>
						<span>Status</span>
					</div>

					<div className={styles.items}>
						{orders?.map((order) => {
							const orderNumber = order.reference?.trim() || order.id.split('-')[0]
							const routeLabel = formatRoute(order)

							return (
								<div className={styles.item} key={order.id}>
									<span className={styles.cell} title={orderNumber}>
										{orderNumber}
									</span>
									<span
										className={cn(styles.cell, styles.comment, {
											[styles.empty]: !order.comment,
										})}
										title={order.comment}
									>
										{order.comment || 'no comment'}
									</span>
									<span className={styles.cell}>
										{`${order.cargo?.length} ${order.cargo?.length > 1 ? 'pcs' : 'pc'}`}
									</span>
									<span className={styles.cell} title={routeLabel}>
										{routeLabel}
									</span>
									<span className={styles.cell}>{order?.pickup_date || EMPTY_STRING}</span>
									<span className={styles.cell} title={order.carrier}>
										{order.carrier || EMPTY_STRING}
									</span>
									<span className={styles.cell}>{order.price || EMPTY_STRING}</span>

									<div
										className={cn(styles.status, {
											[styles.inTransit]:
												order.status === OrderStatusEnum.PICKUP_DONE ||
												order.status === OrderStatusEnum.IN_TRANSIT,
											[styles.delivered]:
												order.status === OrderStatusEnum.DELIVERED ||
												order.status === OrderStatusEnum.APPROVED,
										})}
										title={order?.status_text}
									>
										<div className={styles.statusText}>
											{order.status === OrderStatusEnum.PICKUP_DONE ||
											order.status === OrderStatusEnum.IN_TRANSIT ? (
												<div className={styles.dot} />
											) : (
												<Icon
													type={
														order.status === OrderStatusEnum.DELIVERED ||
														order.status === OrderStatusEnum.APPROVED
															? 'check'
															: 'clock_1'
													}
													size={
														order.status === OrderStatusEnum.DELIVERED ||
														order.status === OrderStatusEnum.APPROVED
															? 12
															: 16
													}
												/>
											)}

											<span>{order?.status_text}</span>
										</div>

										<a
											href={`${ordersBaseUrl}/${order.id}`}
											className={styles.viewLink}
										>
											view
										</a>
									</div>
								</div>
							)
						})}
					</div>

					{pagination && (
						<OrdersPagination
							baseUrl={ordersBaseUrl}
							page={pagination.page}
							totalPages={pagination.totalPages}
							filters={filters}
						/>
					)}
				</div>
			)}
		</div>
	)
}
