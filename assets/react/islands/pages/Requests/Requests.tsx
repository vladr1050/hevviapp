import { type FC } from 'react'

import { OrderType, ShortOrderType } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'

import { MobilePage } from '../MobilePage/MobilePage'

import { RequestsCarrier } from './components/RequestsCarrier/RequestsCarrier'
import { RequestsUser } from './components/RequestsUser/RequestsUser'

interface RequestsPageProps {
	title: string
	isCarrier?: boolean
	orders: ShortOrderType[]
	ordersCarrier: OrderType[]
	device?: DeviceType
}

export const RequestsPage: FC<RequestsPageProps> = (props) => {
	const { title, orders, isCarrier, ordersCarrier, device } = props
	console.log(props)

	const { isMobile } = useDevice(device)

	if (isMobile) return <MobilePage />

	if (isCarrier) return <RequestsCarrier title={title} ordersCarrier={ordersCarrier} />

	return <RequestsUser orders={orders} />
}
