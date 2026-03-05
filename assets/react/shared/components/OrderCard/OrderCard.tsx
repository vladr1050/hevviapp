import { type FC, Suspense, useState } from 'react'
import { MapContainer, Marker, TileLayer } from 'react-leaflet'

import {
	AccountType,
	EMPTY_STRING,
	FormActions,
	OrderStatusEnum,
	OrderType,
} from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'
// @ts-ignore
import L from 'leaflet'

// @ts-ignore
import CustomIcon from './CustomMarker.svg'

import styles from './OrderCard.module.css'

import { CancelModal } from './components/CancelModal/CancelModal'
import { ConfirmModal } from './components/ConfirmModal/ConfirmModal'
import { DeclineModal } from './components/DeclineModal/DeclineModal'
import { RateModal } from './components/RateModal/RateModal'
import { StatusOrder } from './components/StatusOrder/StatusOrder'
import { getDefaultMapData } from './utils'

interface OrderCardProps {
	order: OrderType
	accountType: AccountType
	isRequest?: boolean
}

type ModalIdType = 'confirmSender' | 'cancel' | 'rate' | 'declineCarrier'

export const OrderCard: FC<OrderCardProps> = ({ order, accountType, isRequest }) => {
	const [modalId, setModalId] = useState<ModalIdType>()

	const { defaultPosition, defaultBounds } = getDefaultMapData({
		from: {
			lat: order?.pickup_latitude,
			lng: order?.pickup_longitude,
		},
		to: {
			lat: order?.dropout_latitude,
			lng: order?.dropout_longitude,
		},
	})

	const myIcon: L.Icon = new L.Icon({
		iconUrl: CustomIcon,
		iconSize: new L.Point(40, 40),
		iconAnchor: [20, 30],
	})

	const orderId = order.id.split('-')[0]

	const showId = isRequest

	const isCanceled = order.status === OrderStatusEnum.CANCELLED

	const showInfo =
		!isCanceled &&
		((accountType === 'Sender' && order.status <= OrderStatusEnum.OFFERED) ||
			(accountType === 'Carrier' && isRequest))

	const showStatus =
		!isCanceled &&
		((accountType === 'Sender' && order.status > OrderStatusEnum.OFFERED) ||
			(accountType === 'Carrier' && !isRequest))

	return (
		<>
			<div className={styles.card}>
				<div className={styles.left}>
					{!isRequest && (
						<>
							<div className={styles.titleWrapper}>
								<div className={styles.title}>Offer</div>

								<div className={styles.id}>
									Reference ID
									<span>{orderId}</span>
								</div>
							</div>
						</>
					)}

					<div className={styles.items}>
						<div className={styles.item}>
							<div className={styles.label}>Name</div>
							<div className={styles.value}>{order?.name || EMPTY_STRING}</div>
						</div>

						<div className={styles.row}>
							<div className={styles.item}>
								<div className={styles.label}>Type</div>
								<div className={styles.value}>{order?.type || EMPTY_STRING}</div>
							</div>

							<div className={styles.item}>
								<div className={styles.label}>Size</div>
								<div className={styles.value}>{order?.cargoDimensions || EMPTY_STRING}</div>
							</div>

							<div className={styles.item}>
								<div className={styles.label}>Weight</div>
								<div className={styles.value}>{order?.cargoWeight || EMPTY_STRING}</div>
							</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>Additionals</div>
							<div className={styles.additionals}>
								{order?.stackable && (
									<div className={styles.additional}>
										<div className={styles.icon}>
											<Icon type="check_circle_1" size={20} />
										</div>
										Stackability
									</div>
								)}
								{order?.manipulator_needed && (
									<div className={styles.additional}>
										<div className={styles.icon}>
											<Icon type="check_circle_1" size={20} />
										</div>
										Truck with lift
									</div>
								)}
							</div>
						</div>

						<div className={styles.hr} />

						<div className={styles.routeItem}>
							<div className={styles.routeWrapper}>
								<div className={styles.route} />
							</div>

							<div className={styles.items}>
								<div className={styles.item}>
									<div className={styles.label}>Pickup</div>
									<div className={styles.value}>{order?.address.from || EMPTY_STRING}</div>
								</div>

								<div className={styles.row}>
									<div className={styles.item}>
										<div className={styles.label}>Loading ready</div>
										<div className={styles.value}>{order?.pickup_request_date || EMPTY_STRING}</div>
									</div>

									<div className={styles.item}>
										<div className={styles.label}>Loading window</div>
										<div className={styles.value}>
											{!order.pickup_time_from && !order.pickup_time_to
												? EMPTY_STRING
												: `${order?.pickup_time_from} - ${order?.pickup_time_to}`}
										</div>
									</div>
								</div>

								<div className={styles.hr} />

								<div className={styles.item}>
									<div className={styles.label}>Delivery</div>
									<div className={styles.value}>{order?.address.to || EMPTY_STRING}</div>
								</div>

								<div className={styles.row}>
									<div className={styles.item}>
										<div className={styles.label}>Delivery date</div>
										<div className={styles.value}>{order?.delivery_date || EMPTY_STRING}</div>
									</div>

									<div className={styles.item}>
										<div className={styles.label}>Delivery window</div>
										<div className={styles.value}>
											{!order?.delivery_time_from && !order.delivery_time_to
												? EMPTY_STRING
												: `${order?.delivery_time_from} - ${order?.delivery_time_to}`}
										</div>
									</div>
								</div>
							</div>
						</div>

						<div className={styles.hr} />

						<div className={styles.item}>
							<div className={styles.label}>Comments</div>
							<div className={styles.comments}>{order?.comment || EMPTY_STRING}</div>
						</div>
					</div>
				</div>

				<div
					className={cn(styles.right, {
						[styles.withStatus]: showStatus,
					})}
				>
					<Suspense
						fallback={
							<div className="flex items-center justify-center h-full w-full">Loading...</div>
						}
					>
						<MapContainer
							// @ts-ignore
							center={defaultPosition}
							bounds={defaultBounds}
							boundsOptions={{ padding: [50, 50] }}
							style={{
								width: '100%',
								height: 'calc(100% + 20px)',
								zIndex: 1,
							}}
						>
							<TileLayer
								// @ts-ignore
								attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
								url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
							/>

							{order?.pickup_latitude && order?.pickup_longitude && (
								<Marker
									// @ts-ignore
									icon={myIcon}
									position={{
										lat: order?.pickup_latitude,
										lng: order?.pickup_longitude,
									}}
								/>
							)}

							{order?.dropout_latitude && order?.dropout_longitude && (
								<Marker
									// @ts-ignore
									icon={myIcon}
									position={{
										lat: order?.dropout_latitude,
										lng: order?.dropout_longitude,
									}}
								/>
							)}

							{/* {!!order?.routes.polyline.length && (
								<Polyline pathOptions={{ color: 'black' }} positions={order?.routes.polyline} />
							)} */}
						</MapContainer>
					</Suspense>

					{showId && (
						<div className={styles.id}>
							<div className={styles.label}>Reference ID</div>
							<div className={styles.value}>{orderId}</div>
						</div>
					)}

					{showInfo && (
						<div className={styles.info}>
							<div className={styles.top}>
								<div className={styles.item}>
									<div className={styles.label}>
										Price <br />
										TOTAL
									</div>
									<div className={styles.value}>{order?.price || EMPTY_STRING}</div>
								</div>

								<div className={styles.item}>
									<div className={styles.label}>
										VAT
										<br />
										21%
									</div>
									<div className={styles.value}>VAT {EMPTY_STRING}</div>
								</div>
								<div className={styles.item}>
									<div className={styles.label}>
										Total, inc.
										<br />
										VAT 21$
									</div>
									<div className={styles.value}>TOTAL {EMPTY_STRING}</div>
								</div>
								<div className={styles.item}>
									<div className={styles.label}>
										Platform
										<br />
										commission 10%
									</div>
									<div className={styles.value}>PL {EMPTY_STRING}</div>
								</div>
							</div>

							<div className={styles.hr} />

							<div className={styles.bottom}>
								<div className={styles.bottomLeft}>
									<div
										className={cn({
											[styles.icon]: accountType === 'Sender',
											[styles.avatar]: accountType === 'Carrier',
										})}
										// style={
										// 	accountType === 'Carrier' && !!order?.sender?.image?.length
										// 		? {
										// 				background: `url(${order?.sender?.image}) no-repeat center center/cover `,
										// 			}
										// 		: {}
										// }
									>
										{/* {accountType === 'Sender' ? (
											<Icon type="clock_1" size={30} />
										) : (
											!order?.sender?.image?.length && (
												<>
													{order?.sender?.name.split(' ')[0].charAt(0)}
													{order?.sender?.name.split(' ')[1].charAt(0)}
												</>
											)
										)} */}
										NS {EMPTY_STRING}
									</div>

									<div className={styles.text}>
										{accountType === 'Sender' && (
											<>
												<span className={styles.subtitle}>Delivery time</span>
												<span className={styles.title}>DT {EMPTY_STRING}</span>
											</>
										)}
										{/* {accountType === 'Carrier' && (
											<>
												<span className={styles.title}>{order?.sender?.name}</span>
												<span className={styles.subtitle}>{order?.sender?.company}</span>
											</>
										)} */}
									</div>
								</div>

								<form
									method="POST"
									className={styles.bottomRight}
									action={FormActions.CONFIRM_ORDER}
								>
									{isRequest && (
										<Button
											type="button"
											className="w-full"
											variant="transparent"
											onClick={() => setModalId('declineCarrier')}
										>
											Decline
										</Button>
									)}

									<Button type="submit" name="action" value="CONFIRM_ORDER" className="w-full">
										Confirm
									</Button>
								</form>
							</div>
						</div>
					)}
				</div>

				{showStatus && (
					<StatusOrder order={order} setModalId={setModalId} accountType={accountType} />
				)}
			</div>

			{/* CONFIRM */}
			{accountType === 'Sender' && (
				<Modal
					isOpen={modalId === 'confirmSender'}
					onClose={() => setModalId(undefined)}
					maxWidth="400px"
				>
					<ConfirmModal
						id={order.id}
						from="Riga"
						to="Ventspils"
						onClose={() => setModalId(undefined)}
						email={'example@email.com'}
					/>
				</Modal>
			)}

			{/* CANCEL */}
			<Modal isOpen={modalId === 'cancel'} onClose={() => setModalId(undefined)} maxWidth="400px">
				<CancelModal id={order.id} from="Riga" to="Ventspils" accountType={accountType} />
			</Modal>

			{/* RATE */}
			<Modal isOpen={modalId === 'rate'} onClose={() => setModalId(undefined)} maxWidth="400px">
				<RateModal id={order.id} />
			</Modal>

			{/* DECLINE CARRIER */}
			{accountType === 'Carrier' && (
				<Modal
					isOpen={modalId === 'declineCarrier'}
					onClose={() => setModalId(undefined)}
					maxWidth="400px"
				>
					<DeclineModal id={order.id} from="Riga" to="Ventspils" />
				</Modal>
			)}
		</>
	)
}
