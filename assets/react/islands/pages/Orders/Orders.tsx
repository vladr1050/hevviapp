import type { FC } from 'react'

import { EMPTY_STRING, Routes } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Orders.module.css'

interface OrdersPageProps {
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
	}[]
}

export const OrdersPage: FC<OrdersPageProps> = ({ orders }) => {
	// FIXME
	const _status = 'awaiting' as 'awaiting' | 'delivered' | 'inTransit'

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
							<span>{order.id.split('-')[0]}</span>
							<span className={styles.comment} title={order.comment}>
								{order.comment}
							</span>
							<span>{`${order.item} ${order.item > 1 ? 'pcs' : 'pc'}`}</span>
							<span className="!leading-[14px]">
								{order.address.from}
								<br />
								→
								<br />
								{order.address.to}
								{/* {EMPTY_STRING} */}
							</span>
							<span>{EMPTY_STRING}</span>
							<span>{EMPTY_STRING}</span>
							<span>{order.price}</span>

							<span className={cn(styles.status, { [styles.inTransit]: _status === 'inTransit' })}>
								<div className={styles.text}>
									{_status === 'inTransit' ? (
										<div className={styles.dot} />
									) : (
										<Icon
											type={_status === 'awaiting' ? 'clock_1' : 'check'}
											size={16}
											className="text-white"
										/>
									)}

									{_status === 'inTransit'
										? 'In Transit'
										: _status.charAt(0).toUpperCase() + _status.slice(1)}
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
