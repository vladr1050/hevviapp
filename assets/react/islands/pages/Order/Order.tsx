import { type FC, Suspense, useState } from 'react'
import { MapContainer, Marker, Polyline, TileLayer } from 'react-leaflet'

import { FormActions, OrderStatusId, OrderType, Routes } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'
// @ts-ignore
import L from 'leaflet'

// @ts-ignore
import CustomIcon from './CustomMarker.svg'

import styles from './Order.module.css'

import { CancelRate } from './components/CancelRate/CancelRate'
import { ConfirmRate } from './components/ConfirmRate/ConfirmRate'
import { RateModal } from './components/RateModal/RateModal'

interface OrderPageProps {
	order?: OrderType
}

const DEFAULT_LAT = 56.946845
const DEFAULT_LNG = 24.106075

export const OrderPage: FC<OrderPageProps> = ({ order }) => {
	const [openConfirm, setOpenConfirm] = useState(false)
	const [openCancel, setOpenCancel] = useState(false)
	const [openRateDelivery, setOpenRateDelivery] = useState(false)

	// @ts-ignore
	const statusId = OrderStatusId[order?.status]

	const myIcon: L.Icon = new L.Icon({
		iconUrl: CustomIcon,
		iconSize: new L.Point(40, 40),
	})

	return (
		<>
			<div className={cn('tw-container', styles.page)}>
				<div className={styles.content}>
					<a className={styles.back} href={Routes.ORDERS}>
						<Icon type="arrow_right" className="rotate-180" size={18} />
					</a>

					<div className={styles.card}>
						<div className={styles.left}>
							<div className={styles.titleWrapper}>
								<h1 className={styles.title}>Offer</h1>

								<div className={styles.id}>
									Reference ID
									<span>{order?.id}</span>
								</div>
							</div>

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

							{statusId === 0 && (
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
										<div className={styles.deliveryWrapper}>
											<div className={styles.icon}>
												<Icon type="clock_1" size={30} />
											</div>

											<div className={styles.text}>
												Delivery time
												<span>{order?.deliveryTime}</span>
											</div>
										</div>

										<form method="POST" action={FormActions.CONFIRM_ORDER}>
											<Button
												type="submit"
												name="action"
												value="CONFIRM_ORDER"
												className="w-[234px]"
											>
												Confirm
											</Button>
										</form>
									</div>
								</div>
							)}
						</div>

						{statusId > 0 && (
							<div className={styles.statusInfo}>
								<div className={styles.title}>Status</div>
								<div className={styles.statusWrapper}>
									<div className={styles.status}>
										Awaiting Payment
										<div className={styles.dot} />
										{statusId >= 1 && (
											<div className={styles.active}>
												Payment successful
												<div className={cn(styles.icon, { [styles.activeIcon]: statusId > 1 })}>
													<Icon type={statusId === 1 ? 'box' : 'check_circle_1'} size={20} />
												</div>
											</div>
										)}
									</div>

									<div className={styles.line} />

									<div className={styles.status}>
										Carrier matched
										<div className={styles.dot} />
										{statusId >= 2 && (
											<div className={styles.active}>
												Carrier matched
												<div className={cn(styles.icon, { [styles.activeIcon]: statusId > 2 })}>
													<Icon type={statusId === 2 ? 'box' : 'check_circle_1'} size={20} />
												</div>
											</div>
										)}
									</div>

									<div className={styles.line} />

									<div className={styles.status}>
										Awaiting pickup
										<div className={styles.dot} />
										{statusId >= 3 && (
											<div className={styles.active}>
												Awaiting pickup
												<div className={cn(styles.icon, { [styles.activeIcon]: statusId > 3 })}>
													<Icon type={statusId === 3 ? 'box' : 'check_circle_1'} size={20} />
												</div>
											</div>
										)}
									</div>

									<div className={styles.line} />

									<div className={styles.status}>
										In transit
										<div className={styles.dot} />
										{statusId >= 4 && (
											<div className={styles.active}>
												In transit
												<div className={cn(styles.icon, { [styles.activeIcon]: statusId > 4 })}>
													<Icon type={statusId === 4 ? 'box' : 'check_circle_1'} size={20} />
												</div>
											</div>
										)}
									</div>

									<div className={styles.line} />

									<div className={styles.status}>
										<div className={styles.statusTitleWrapper}>
											Delivery
											<span className="text-red-500">
												ETA: Tomorrow,
												<br />
												10:00 - 18:00
											</span>
										</div>

										<Icon type="mark_map" size={24} className="!translate-x-0.5" />

										{statusId === 6 && (
											<div className={styles.active}>
												<div className={styles.statusTitleWrapper}>
													Delivered
													<span className="text-red-500">
														Today at
														<br />
														10:00
													</span>
												</div>

												<div className={cn(styles.icon, styles.activeIcon)}>
													<Icon type="check_circle_1" size={20} />
												</div>
											</div>
										)}
									</div>
								</div>

								{statusId < 4 && (
									<button
										className={styles.button}
										type="button"
										onClick={() => setOpenCancel(true)}
									>
										Cancel order
									</button>
								)}

								{(statusId === 4 || statusId === 5) && <div />}

								{statusId === 6 && (
									<Button
										type="button"
										onClick={() => setOpenRateDelivery(true)}
										className="!w-full"
									>
										Rate delivery
									</Button>
								)}
							</div>
						)}
					</div>
				</div>
			</div>

			<Modal isOpen={openConfirm} onClose={() => setOpenConfirm(false)} maxWidth="400px">
				<ConfirmRate
					id={order?.id}
					from="Riga"
					to="Ventspils"
					onClose={() => setOpenConfirm(false)}
					email={'example@email.com'}
				/>
			</Modal>

			<Modal isOpen={openCancel} onClose={() => setOpenCancel(false)} maxWidth="400px">
				<CancelRate id={order?.id} from="Riga" to="Ventspils" />
			</Modal>

			<Modal isOpen={openRateDelivery} onClose={() => setOpenRateDelivery(false)} maxWidth="400px">
				<RateModal id={order?.id} />
			</Modal>
		</>
	)
}
