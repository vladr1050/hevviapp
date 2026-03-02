import { type FC, Suspense, useState } from 'react'
import { MapContainer, Marker, Polyline, TileLayer } from 'react-leaflet'

import { OrderType, Routes } from '@config/constants'
import { useLocation } from '@hooks/useLocation'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'

import styles from './Requests.module.css'

import { InputButton } from './components/InputButton/InputButton'
import { ModalContent } from './components/ModalContent/ModalContent'

interface RequestsPageProps {
	// SENDER
	ordersSender?: {
		//
		id: string
		name: string
		count: number
		route: { from: string; to: string }
	}[]
	latestRoutes?: { from: string; to: string }[]

	// CARRIER
	ordersCarrier?: OrderType[]
}

const DEFAULT_LAT = 56.946845
const DEFAULT_LNG = 24.106075

export type CalculateModalType = 'what' | 'where' | 'when' | 'calculate' | undefined

export const RequestsPage: FC<RequestsPageProps> = ({
	ordersSender,
	ordersCarrier,
	latestRoutes,
}) => {
	const [activeButton, setActiveButton] = useState<CalculateModalType>()

	const { pathname } = useLocation()

	// const _currentOrderId = pathname.split('/').pop()
	const _currentOrderId = '00001'
	const [curOrderId, setCurOrderId] = useState(_currentOrderId)

	console.log({ curOrderId, order: ordersCarrier?.filter((order) => order.id === curOrderId) })

	if (typeof ordersCarrier !== 'undefined')
		return (
			<div className={cn('tw-container', styles.page, styles.carrier)}>
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

						<a href={Routes.ORDERS} className={styles.link}>
							View orders
						</a>
					</div>
				)}

				{!!ordersCarrier?.length && (
					<div className={styles.card}>
						{ordersCarrier
							.filter((order) => order.id === curOrderId)
							.map((order) => (
								<>
									<div className={styles.left}>
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
													<div className={cn(styles.value, '!font-bold')}>
														{order?.routes.from.address}
													</div>
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
													<div className={cn(styles.value, '!font-bold')}>
														{order?.routes.to.address}
													</div>
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

									<div className={styles.right}>
										{/* <Suspense
											fallback={
												<div className="flex items-center justify-center h-full w-full">
													Loading...
												</div>
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
													<Polyline
														pathOptions={{ color: 'black' }}
														positions={order?.routes.polyline}
													/>
												)}
											</MapContainer>
										</Suspense> */}

										<div className={styles.id}>
											<div className={styles.label}>Reference ID</div>
											<div className={styles.value}>{order?.id}</div>
										</div>
									</div>
								</>
							))}
					</div>
				)}
			</div>
		)

	return (
		<>
			<div className={cn('tw-container', styles.page, styles.sender)}>
				<div className={styles.mainWrapper}>
					<div className={styles.main}>
						<h1 className={styles.title}>
							<span className={styles.light}>Heavy cargo delivery.</span>
							<span>Order in seconds.</span>
							<span>Get in 48 hours.</span>
						</h1>
						<div className={styles.inputButtons}>
							<InputButton
								label="What"
								placeholder="Fill cargo parameters"
								className={cn(styles.inputButton)}
								onClick={() => setActiveButton('what')}
							/>
							<InputButton
								label="Where"
								placeholder="Enter destination"
								className={cn(styles.inputButton)}
								onClick={() => setActiveButton('where')}
							/>
							<InputButton
								label="When"
								placeholder="Add date"
								className={cn(styles.inputButton)}
								onClick={() => setActiveButton('when')}
							/>

							<Button type="button" onClick={() => setActiveButton('calculate')}>
								Calculate
							</Button>
						</div>
					</div>

					<div className={styles.description}>
						<div className={styles.item}>
							<Icon type="vehicle" size={20} className={styles.icon} />
							500+ vehicle fleet
						</div>
						<div className={styles.item}>
							<Icon type="trust" size={20} className={styles.icon} />
							50+ trusted carriers
						</div>
						<div className={styles.item}>
							<Icon type="box" size={20} className={styles.icon} />
							10,000 m³ capacity available
						</div>
					</div>
				</div>
				<div className={cn(styles.ordersWrapper, { ['!px-0']: !!ordersSender?.length })}>
					{!ordersSender?.length ? (
						<>
							<h2 className={styles.title}>How does it work?</h2>

							<div className={styles.description}>
								<div className={styles.item}>
									<div className={styles.icon}>
										<Icon type="calculate_price" size={32} />
									</div>
									<div className={styles.text}>
										Calculate price
										<span>in seconds</span>
									</div>
								</div>

								<Icon type="arrow_right" size={23} />

								<div className={styles.item}>
									<div className={styles.icon}>
										<Icon type="confirm_order" size={32} />
									</div>
									<div className={styles.text}>
										Confirm the order
										<span>and make a payment</span>
									</div>
								</div>

								<Icon type="arrow_right" size={23} />

								<div className={styles.item}>
									<div className={styles.icon}>
										<Icon type="vehicle_drive" size={32} />
									</div>
									<div className={styles.text}>
										Get the delivery
										<span>in 48 hours</span>
									</div>
								</div>
							</div>
						</>
					) : (
						<>
							<div className={styles.header}>
								<div className={styles.item}>
									<div className={styles.icon}>
										<Icon type="searched_history" size={16} />
									</div>
									Your recent searched
								</div>
								<div className={styles.item}>
									<div className={styles.icon}>
										<Icon type="previous_orders" size={16} />
									</div>
									Your previous orders
								</div>
							</div>

							<div className={styles.orders}>
								{ordersSender?.map((order, index) => (
									<div className={styles.order} key={index}>
										<div className={styles.name}>{order.name}</div>
										<div className="">{`${order.count} pallet${order.count > 1 ? 's' : ''}`}</div>
										<div className="">
											{order.route.from} → {order.route.to}
										</div>
										<button className={styles.button}>Pielietot</button>
									</div>
								))}
							</div>
						</>
					)}
				</div>
			</div>

			<Modal
				isOpen={!!activeButton}
				onClose={() => setActiveButton(undefined)}
				disableCloseButton
				maxWidth="1200px"
			>
				<ModalContent
					activeButton={activeButton}
					setActiveButton={setActiveButton}
					latestRoutes={latestRoutes}
				/>
			</Modal>
		</>
	)
}
