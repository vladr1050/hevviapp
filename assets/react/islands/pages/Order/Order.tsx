import { type FC } from 'react'

import { OrderCard } from '@components/OrderCard/OrderCard'
import { OrderType, Routes } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Order.module.css'

interface OrderPageProps {
	order: OrderType
	accountType: 'sender' | 'carrier'
}

export const OrderPage: FC<OrderPageProps> = ({ accountType, order }) => {
	return (
		<div className={cn('tw-container', styles.page)}>
			<div className={styles.content}>
				<a className={styles.back} href={Routes.ORDERS}>
					<Icon type="arrow_right" className="rotate-180" size={18} />
				</a>

				<OrderCard order={order} accountType={accountType} />
			</div>
		</div>
	)
}
