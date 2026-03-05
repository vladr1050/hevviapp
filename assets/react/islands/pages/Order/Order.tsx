import { type FC } from 'react'

import { OrderCard } from '@components/OrderCard/OrderCard'
import { AccountType, OrderType, Routes } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Order.module.css'

interface OrderPageProps {
	title: string
	order: OrderType
	accountType: AccountType
	csrf_token: string
}

export const OrderPage: FC<OrderPageProps> = (props) => {
	const { title, order, accountType, csrf_token } = props

	console.log(props)

	return (
		<div className={cn('tw-container', styles.page)}>
			<div className={styles.content}>
				<a className={styles.back} href={Routes.ORDERS}>
					<Icon type="arrow_right" className="rotate-180" size={18} />
				</a>

				<OrderCard title={title} order={order} accountType={accountType} csrfToken={csrf_token} />
			</div>
		</div>
	)
}
