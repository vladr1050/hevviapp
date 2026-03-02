import type { FC } from 'react'

import { Routes } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Orders.module.css'

interface OrdersPageProps {
	orders?: {
		id: string
		comment: string
		item: string
		address: { from: string; to: string }
		pickupDate: string
		type: string
		price: string
		status: 'awaiting' | 'delivered' | 'inTransit'
	}[]
}

export const OrdersPage: FC<OrdersPageProps> = ({ orders }) => {
	return (
		<div className={cn('tw-container', styles.page)}>
			<h1 className={styles.title}>
				My Orders {!!orders?.length && <span>({orders?.length})</span>}
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
							<span>{order.id}</span>
							<span className={styles.comment} title={order.comment}>
								{order.comment}
							</span>
							<span>{order.item}</span>
							<span>
								{order.address.from} → {order.address.to}
							</span>
							<span>{order.pickupDate}</span>
							<span>{order.type}</span>
							<span>{order.price}</span>

							<span
								className={cn(styles.status, { [styles.inTransit]: order.status === 'inTransit' })}
							>
								<div className={styles.text}>
									{order.status === 'inTransit' ? (
										<div className={styles.dot} />
									) : (
										<Icon
											type={order.status === 'awaiting' ? 'clock_1' : 'check'}
											size={16}
											className="text-white"
										/>
									)}

									{order.status === 'inTransit'
										? 'In Transit'
										: order.status.charAt(0).toUpperCase() + order.status.slice(1)}
								</div>

								<a href={`${Routes.ORDERS}/${order.id}`} className={styles.link}>
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
