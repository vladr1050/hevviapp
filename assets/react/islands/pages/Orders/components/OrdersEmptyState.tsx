import type { FC } from 'react'

import { Routes } from '@config/constants'

import ordersEmptyPallet from '../images/orders-empty-pallet.png'
import styles from './OrdersEmptyState.module.css'

interface OrdersEmptyStateProps {
	isCarrier?: boolean
}

/** Figma 316:10853 — My Orders empty. */
export const OrdersEmptyState: FC<OrdersEmptyStateProps> = ({ isCarrier }) => {
	const href = isCarrier ? Routes.CARRIER_REQUESTS : Routes.USER_REQUESTS

	return (
		<div className={styles.empty}>
			<img
				src={ordersEmptyPallet}
				alt=""
				width={908}
				height={456}
				className={styles.illustration}
				loading="lazy"
				decoding="async"
			/>

			<div className={styles.textBlock}>
				<h2 className={styles.heading}>No orders yet</h2>
				<p className={styles.description}>Once you place an order, it will show up here.</p>
			</div>

			<a href={href} className={styles.cta}>
				Make first order
			</a>
		</div>
	)
}
