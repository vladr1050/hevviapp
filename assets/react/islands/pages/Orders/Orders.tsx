import type { FC } from 'react'

import { EMPTY_STRING, OrderStatusEnum, Routes } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Orders.module.css'

interface OrdersPageProps {
	title: string
	orders?: {
		address: {
			from: string
			to: string
		}
		comment: string
		id: string
		item: number
		price: string
		status: number
		status_text: string

		carrier?: string
		pickup_date?: string
	}[]
}

export const OrdersPage: FC<OrdersPageProps> = (props) => {
	const { title, orders } = props
	console.log(props)

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
						<div className={styles.item} key={index}>
							<span>{order.id.split('-')[0]}</span>
							<span className={styles.comment} title={order.comment}>
								{order.comment}
							</span>
							<span>{`${order.item} ${order.item > 1 ? 'pcs' : 'pc'}`}</span>
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
									{OrderStatusEnum.IN_TRANSIT === order.status ? (
										<div className={styles.dot} />
									) : (
										<Icon
											type={OrderStatusEnum.DELIVERED === order.status ? 'check' : 'clock_1'}
											size={16}
											className="text-white"
										/>
									)}

									<span>{order?.status_text}</span>
								</div>

								<a href={`${Routes.ORDERS}/${order.id}`} className={styles.link} title="">
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
