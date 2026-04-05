import type { FC } from 'react'

import { EMPTY_STRING, OrderStatusEnum, OrderType, Routes } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Orders.module.css'

import { MobilePage } from '../MobilePage/MobilePage'

interface OrdersPageProps {
	title: string
	isCarrier?: boolean
	orders?: OrderType[]
	device?: DeviceType
}

export const OrdersPage: FC<OrdersPageProps> = (props) => {
	const { title, orders, isCarrier, device } = props

	const { isMobile } = useDevice(device)

	if (isMobile) return <MobilePage />

	return (
		<div className={cn('tw-container', styles.page)}>
			<h1 className={styles.title}>
				{title} {!!orders?.length && <span>({orders?.length})</span>}
			</h1>

			<div className={styles.tableWrapper}>
				<div className={styles.header}>
					<span>ID</span>
					<span>Comment</span>
					<span>Item</span>
					<span>Delivery Address</span>
					<span>Pickup Date</span>
					<span>Type</span>
					<span>Price</span>
					<span>Status</span>
				</div>

				<div className={styles.items}>
					{orders?.map((order, index) => (
						<div className={styles.item} key={index}>
							<span>{order.id.split('-')[0]}</span>
							<span
								className={cn(styles.comment, { [styles.empty]: !order.comment })}
								title={order.comment}
							>
								{order.comment || 'no comment'}
							</span>
							<span>{`${order.cargo?.length} ${order.cargo?.length > 1 ? 'pcs' : 'pc'}`}</span>
							<span className="truncate !text-sm" title={order.address.to}>
								{order.address.to}
							</span>
							<span>{order?.pickup_date || EMPTY_STRING}</span>
							<span>{EMPTY_STRING}</span>
							<span>{order.price}</span>

							<span
								className={cn(styles.status, {
									[styles.inTransit]:
										order.status === OrderStatusEnum.PICKUP_DONE ||
										order.status === OrderStatusEnum.IN_TRANSIT,
									[styles.delivered]: order.status === OrderStatusEnum.DELIVERED,
								})}
								title={order?.status_text}
							>
								<div className={styles.text}>
									{order.status === OrderStatusEnum.PICKUP_DONE ||
									order.status === OrderStatusEnum.IN_TRANSIT ? (
										<div className={styles.dot} />
									) : (
										<Icon
											type={order.status === OrderStatusEnum.DELIVERED ? 'check' : 'clock_1'}
											size={order.status === OrderStatusEnum.DELIVERED ? 12 : 16}
										/>
									)}

									<span>{order?.status_text}</span>
								</div>

								<a
									href={`${isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS}/${order.id}`}
									className={styles.link}
									title=""
								>
									view
								</a>
							</span>
						</div>
					))}
				</div>
			</div>
		</div>
	)
}
