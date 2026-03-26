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
	console.log(props)

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
					<span>Address</span>
					<span>Pickup Date</span>
					<span>Type</span>
					<span>Price</span>
					<span>Status</span>
				</div>

				<div className={styles.items}>
					{orders?.map((order, index) => (
						<div
							className={styles.item}
							key={index}
							onClick={() => {
								console.log(order)
							}}
						>
							<span>{order.id.split('-')[0]}</span>
							<span className={styles.comment} title={order.comment}>
								{order.comment}
							</span>
							<span>{`${order.cargo?.length} ${order.cargo?.length > 1 ? 'pcs' : 'pc'}`}</span>
							<span className="!text-xs !leading-[12px]">
								{order.address.from}
								<br />
								→
								<br />
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
											size={16}
											className="text-white"
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
