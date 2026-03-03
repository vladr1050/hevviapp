import { type FC, Suspense, useState } from 'react'
import { MapContainer, Marker, Polyline, TileLayer } from 'react-leaflet'

import { FormActions, OrderStatusId, OrderType } from '@config/constants'
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

interface OrderCardProps {
	order: OrderType
	accountType?: 'sender' | 'carrier'
	isRequest?: boolean
}

const DEFAULT_LAT = 56.946845
const DEFAULT_LNG = 24.106075

type ModalIdType = 'confirmSender' | 'cancel' | 'rate' | 'declineCarrier'

export const OrderCard: FC<OrderCardProps> = ({ order, accountType = 'sender', isRequest }) => {
	const [modalId, setModalId] = useState<ModalIdType>()

	const statusId = OrderStatusId[order?.status]

	const myIcon: L.Icon = new L.Icon({
		iconUrl: CustomIcon,
		iconSize: new L.Point(40, 40),
		iconAnchor: [20, 30],
	})

	const showId = isRequest

	const showInfo =
		(accountType === 'sender' && statusId === 0) || (accountType === 'carrier' && isRequest)

	const showStatus =
		(accountType === 'sender' && statusId > 0) || (accountType === 'carrier' && !isRequest)

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
									<span>{order?.id}</span>
								</div>
							</div>
						</>
					)}

					<div className={styles.items}>
						<div className={styles.item}>
							<div className={styles.label}>Name</div>
							<div className={styles.value}>{order?.name}</div>
						</div>

						<div className={styles.row}>
							<div className={styles.item}>
								<div className={styles.label}>Type</div>
								<div className={styles.value}>{order?.type}</div>
							</div>

							<div className={styles.item}>
								<div className={styles.label}>Size</div>
								<div className={styles.value}>{order?.size}</div>
							</div>

							<div className={styles.item}>
								<div className={styles.label}>Weight</div>
								<div className={styles.value}>{order?.weight}</div>
							</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>Additionals</div>
							<div className={styles.additionals}>
								{order?.additionals.stackability && (
									<div className={styles.additional}>
										<div className={styles.icon}>
											<Icon type="check_circle_1" size={20} />
										</div>
										Stackability
									</div>
								)}
								{order?.additionals.lift && (
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
									<div className={styles.value}>{order?.routes.from.address}</div>
								</div>

								<div className={styles.row}>
									<div className={styles.item}>
										<div className={styles.label}>Loading ready</div>
										<div className={styles.value}>{order?.routes.from.loadingReady}</div>
									</div>

									<div className={styles.item}>
										<div className={styles.label}>Loading window</div>
										<div className={styles.value}>{order?.routes.from.loadingWindow}</div>
									</div>

									<div className={styles.item}>
										<div className={styles.label}>Delivery date</div>
										<div className={styles.value}>{order?.routes.from.deliveryDate}</div>
									</div>
								</div>

								<div className={styles.hr} />

								<div className={styles.item}>
									<div className={styles.label}>Delivery</div>
									<div className={styles.value}>{order?.routes.to.address}</div>
								</div>

								<div className={styles.row}>
									<div className={styles.item}>
										<div className={styles.label}>Loading ready</div>
										<div className={styles.value}>{order?.routes.to.loadingReady}</div>
									</div>

									<div className={styles.item}>
										<div className={styles.label}>Delivery window</div>
										<div className={styles.value}>{order?.routes.to.deliveryWindow}</div>
									</div>

									<div className={styles.item}>
										<div className={styles.label}>Delivery date</div>
										<div className={styles.value}>{order?.routes.to.deliveryDate}</div>
									</div>
								</div>
							</div>
						</div>

						<div className={styles.hr} />

						<div className={styles.item}>
							<div className={styles.label}>Comments</div>
							<div className={styles.comments}>{order?.comments}</div>
						</div>
					</div>
				</div>

				<div className={styles.right}>
					<Suspense
						fallback={
							<div className="flex items-center justify-center h-full w-full">Loading...</div>
						}
					>
						<MapContainer
							// @ts-ignore
							center={[DEFAULT_LAT, DEFAULT_LNG]}
							zoom={10}
							// scrollWheelZoom={false}
							style={{ width: '100%', height: 'calc(100% + 20px)', zIndex: 1 }}
						>
							<TileLayer
								// @ts-ignore
								attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
								url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
							/>

							{order?.routes.from.position.lat && order?.routes.from.position.lng && (
								<Marker
									// @ts-ignore
									icon={myIcon}
									position={{
										lat: order?.routes.from.position.lat,
										lng: order?.routes.from.position.lng,
									}}
								/>
							)}

							{order?.routes.to.position.lat && order?.routes.to.position.lng && (
								<Marker
									// @ts-ignore
									icon={myIcon}
									position={{
										lat: order?.routes.to.position.lat,
										lng: order?.routes.to.position.lng,
									}}
								/>
							)}

							{!!order?.routes.polyline.length && (
								<Polyline pathOptions={{ color: 'black' }} positions={order?.routes.polyline} />
							)}
						</MapContainer>
					</Suspense>

					{showId && (
						<div className={styles.id}>
							<div className={styles.label}>Reference ID</div>
							<div className={styles.value}>{order?.id}</div>
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
									<div className={styles.value}>{order?.price}</div>
								</div>

								<div className={styles.item}>
									<div className={styles.label}>
										VAT
										<br />
										21%
									</div>
									<div className={styles.value}>{order?.vat}</div>
								</div>
								<div className={styles.item}>
									<div className={styles.label}>
										Total, inc.
										<br />
										VAT 21$
									</div>
									<div className={styles.value}>{order?.total}</div>
								</div>
								<div className={styles.item}>
									<div className={styles.label}>
										Platform
										<br />
										commission 10%
									</div>
									<div className={styles.value}>{order?.platform}</div>
								</div>
							</div>

							<div className={styles.hr} />

							<div className={styles.bottom}>
								<div className={styles.bottomLeft}>
									<div
										className={cn({
											[styles.icon]: accountType === 'sender',
											[styles.avatar]: accountType === 'carrier',
										})}
										style={
											accountType === 'carrier' && !!order?.sender?.image?.length
												? {
														background: `url(${order?.sender?.image}) no-repeat center center/cover `,
													}
												: {}
										}
									>
										{accountType === 'sender' ? (
											<Icon type="clock_1" size={30} />
										) : (
											!order?.sender?.image?.length && (
												<>
													{order?.sender?.name.split(' ')[0].charAt(0)}
													{order?.sender?.name.split(' ')[1].charAt(0)}
												</>
											)
										)}
									</div>

									<div className={styles.text}>
										{accountType === 'sender' && (
											<>
												<span className={styles.subtitle}>Delivery time</span>
												<span className={styles.title}>{order?.deliveryTime}</span>
											</>
										)}
										{accountType === 'carrier' && (
											<>
												<span className={styles.title}>{order?.sender?.name}</span>
												<span className={styles.subtitle}>{order?.sender?.company}</span>
											</>
										)}
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
			{accountType === 'sender' && (
				<Modal
					isOpen={modalId === 'confirmSender'}
					onClose={() => setModalId(undefined)}
					maxWidth="400px"
				>
					<ConfirmModal
						id={order?.id}
						from="Riga"
						to="Ventspils"
						onClose={() => setModalId(undefined)}
						email={'example@email.com'}
					/>
				</Modal>
			)}

			{/* CANCEL */}
			<Modal isOpen={modalId === 'cancel'} onClose={() => setModalId(undefined)} maxWidth="400px">
				<CancelModal id={order?.id} from="Riga" to="Ventspils" accountType={accountType} />
			</Modal>

			{/* RATE */}
			<Modal isOpen={modalId === 'rate'} onClose={() => setModalId(undefined)} maxWidth="400px">
				<RateModal id={order?.id} />
			</Modal>

			{/* DECLINE CARRIER */}
			{accountType === 'carrier' && (
				<Modal
					isOpen={modalId === 'declineCarrier'}
					onClose={() => setModalId(undefined)}
					maxWidth="400px"
				>
					<DeclineModal id={order?.id} from="Riga" to="Ventspils" />
				</Modal>
			)}
		</>
	)
}
