import { type FC } from 'react'

import { OrderCard } from '@components/OrderCard/OrderCard'
import { OrderType, Routes } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Order.module.css'

import { MobilePage } from '../MobilePage/MobilePage'

interface OrderPageProps {
	title: string
	order: OrderType
	csrf_token: string
	update_status_csrf_token?: string
	isCarrier?: boolean
	device?: DeviceType
}

export const OrderPage: FC<OrderPageProps> = (props) => {
	const { title, order, csrf_token, update_status_csrf_token, isCarrier, device } = props
	console.log(props)

	const { isMobile } = useDevice(device)

	if (isMobile) return <MobilePage />

	return (
		<div className={cn('tw-container', styles.page)}>
			<div className={styles.content}>
				<a className={styles.back} href={isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS}>
					<Icon type="arrow_right" className="rotate-180" size={18} />
				</a>

			<OrderCard
				title={title}
				order={order}
				isCarrier={isCarrier}
				csrfToken={csrf_token}
				updateStatusCsrfToken={update_status_csrf_token}
			/>
			</div>
		</div>
	)
}
