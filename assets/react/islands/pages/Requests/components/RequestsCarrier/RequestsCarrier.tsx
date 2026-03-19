import { type FC, useState } from 'react'

import { OrderCard } from '@components/OrderCard/OrderCard'
import { OrderType, Routes } from '@config/constants'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from '../../Requests.module.css'

interface RequestsCarrierProps {
	title: string
	ordersCarrier: OrderType[]
}

export const RequestsCarrier: FC<RequestsCarrierProps> = ({ title, ordersCarrier }) => {
	const [curOrderIndex, setCurOrderIndex] = useState(0)

	return (
		<div className={cn('tw-container', styles.page, styles.carrier)}>
			<div className={styles.main}>
				<div className={styles.title}>
					Requests {!!ordersCarrier?.length && <span>({ordersCarrier.length})</span>}
				</div>

				{!ordersCarrier?.length && (
					<div className={styles.empty}>
						<Icon type="pallet" size={220} className={styles.icon} />

						<div className={styles.text}>
							<div className={styles.title}>No new requests yet</div>
							<div className={styles.subtitle}>
								We will notify you when there will be a new request
							</div>
						</div>

						<a href={Routes.CARRIER_ORDERS} className={styles.link}>
							View orders
						</a>
					</div>
				)}

				{!!ordersCarrier?.length && (
					<div className={styles.ordersWrapper}>
						<div
							className={cn(styles.ordersSlide, {
								[styles.someSlides]: ordersCarrier.length > 1,
							})}
						>
							{ordersCarrier.length > 1 && (
								<div className={styles.leftCard}>
									<div className={styles.line} />

									<button
										type="button"
										className={styles.arrowButton}
										onClick={() =>
											setCurOrderIndex((prev) =>
												prev - 1 < 0 ? ordersCarrier.length - 1 : prev - 1
											)
										}
									>
										<Icon type="arrow_right" size={18} className="rotate-180" />
									</button>
								</div>
							)}

							<OrderCard
								title={title}
								order={ordersCarrier?.[curOrderIndex] || ordersCarrier[0]}
								isCarrier
								isRequest
							/>

							{ordersCarrier.length > 1 && (
								<div className={styles.rightCard}>
									<div className={styles.line} />

									<button
										type="button"
										className={styles.arrowButton}
										onClick={() =>
											setCurOrderIndex((prev) =>
												prev + 1 > ordersCarrier.length - 1 ? 0 : prev + 1
											)
										}
									>
										<Icon type="arrow_right" size={18} />
									</button>
								</div>
							)}
						</div>

						{ordersCarrier.length > 1 && (
							<div className={styles.dots}>
								{ordersCarrier.map((_, idx) => (
									<div
										key={idx}
										className={cn(styles.dot, { [styles.active]: idx === curOrderIndex })}
									/>
								))}
							</div>
						)}
					</div>
				)}
			</div>

			<div className={styles.footer}>
				<div className={styles.item}>
					<Icon type="route_map" size={20} />
					Fewer Empty Miles
				</div>
				<div className={styles.item}>
					<Icon type="right_box" size={20} />
					Less Manual Dispatch
				</div>
				<div className={styles.item}>
					<Icon type="confirm_order" size={20} />
					Predictable Payments
				</div>
			</div>
		</div>
	)
}
