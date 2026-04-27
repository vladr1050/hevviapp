import { type FC, Fragment, Suspense, useState } from 'react'
import { MapContainer, Marker, TileLayer } from 'react-leaflet'

import {
	EMPTY_STRING,
	FormActions,
	OrderStatusEnum,
	OrderType,
	carrierCancelOrderUrl,
	carrierConfirmRequestUrl,
	carrierDeclineRequestUrl,
	userAbandonOrderUrl,
	userCancelOrderUrl,
	userConfirmOrderUrl,
} from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'
import { downloadFileByUrl } from '@utils/file/downloadFile'
import { getFileCategory } from '@utils/file/getFileCategory'
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
	title: string
	order: OrderType
	isCarrier?: boolean
	isRequest?: boolean
	csrfToken?: string
	cancelCsrfToken?: string
	abandonCsrfToken?: string
	updateStatusCsrfToken?: string
}

type ModalIdType = 'confirmSender' | 'cancel' | 'rate' | 'declineCarrier'

export const OrderCard: FC<OrderCardProps> = ({
	title,
	order,
	isCarrier,
	isRequest,
	csrfToken,
	cancelCsrfToken,
	abandonCsrfToken,
	updateStatusCsrfToken,
}) => {
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

	const orderReferenceDisplay = order.reference?.trim() || order.id.split('-')[0]

	const showId = isRequest

	const isCanceled = order.status === OrderStatusEnum.CANCELLED
	const isDelivered = order.status === OrderStatusEnum.DELIVERED
	const isOffered = order.status <= OrderStatusEnum.OFFERED

	const showInfo =
		!isCanceled &&
		((!isCarrier && order.status <= OrderStatusEnum.OFFERED) || (isCarrier && isRequest))

	const showStatus =
		!isCanceled &&
		((!isCarrier && order.status > OrderStatusEnum.OFFERED) || (isCarrier && !isRequest))

	const SenderTotalPriceBlock = () => (
		<div className="grid grid-cols-5 gap-3 w-full">
			<div className="col-span-4" aria-hidden="true" />
			<div className={styles.item}>
				<div className={styles.label}>Total</div>
				<div className={styles.value}>
					{order?.sender_total ?? order?.brutto ?? EMPTY_STRING}
				</div>
			</div>
		</div>
	)

	const CarrierPriceBlock = () => (
		<div className="grid grid-cols-4 gap-3 w-full">
			<div className={styles.item}>
				<div className={styles.label}>Base fee</div>
				<div className={styles.value}>{order?.price || EMPTY_STRING}</div>
			</div>

			<div className={styles.item}>
				<div className={styles.label}>PVN likme</div>
				<div className={styles.value}>
					{order?.carrier_freight_vat_rate_display ?? '21%'}
				</div>
			</div>

			<div className={styles.item}>
				<div className={styles.label}>VAT 21%</div>
				<div className={styles.value}>
					{order?.carrier_freight_vat ?? order?.vat ?? EMPTY_STRING}
				</div>
			</div>

			<div className={styles.item}>
				<div className={styles.label}>Total</div>
				<div className={styles.value}>
					{order?.carrier_freight_total ?? order?.brutto ?? EMPTY_STRING}
				</div>
			</div>
		</div>
	)

	const PriceBlock = () => (!isCarrier ? <SenderTotalPriceBlock /> : <CarrierPriceBlock />)

	return (
		<>
			<div className={cn(styles.card, { [styles.isCarrier]: isCarrier })}>
				<div className={styles.left}>
					{!isRequest && (
						<>
							<div className={styles.titleWrapper}>
								<div className={styles.title}>{title}</div>

								<div className={styles.id}>
									Reference ID
									<span>{orderReferenceDisplay}</span>
								</div>
							</div>
						</>
					)}

					<div className={styles.items}>
						<div className={styles.itemsWrapper}>
							<div className={styles.label}>Cargo</div>

							<div className={styles.itemsContent}>
								{/* CARGO */}
								{order.cargo.map((item, index) => (
									<Fragment key={index}>
										<div className={styles.cargo}>
											<div className={styles.index}>{index + 1}.</div>

											{/* NAME */}
											<div className={cn(styles.value, '!truncate')} title={item?.name}>
												{item?.name || EMPTY_STRING}
											</div>

											{/* Q-ty */}
											<div className={styles.value}>
												{!item?.quantity ? (
													EMPTY_STRING
												) : (
													<>
														{item?.quantity}{' '}
														<span className="font-normal text-xs">
															{item?.quantity > 1 ? 'pcs' : 'pc'}
														</span>
													</>
												)}
											</div>

											{/* Size */}
											<div className={styles.value}>
												{!item?.dimensions
													? EMPTY_STRING
													: item?.dimensions?.split('x').map((d, i, arr) => {
															if (i === arr.length - 1)
																return (
																	<>
																		{d} <span className="font-normal text-xs">cm</span>
																	</>
																)
															return (
																<>
																	{d} <span className="font-normal text-xs">x</span>{' '}
																</>
															)
														})}
											</div>

											{/* weight*/}
											<div className={styles.value}>
												{!item?.weight ? (
													EMPTY_STRING
												) : (
													<>
														{item?.weight} <span className="font-normal text-xs">kg</span>
													</>
												)}
											</div>
										</div>

										<div className={styles.hr} />
									</Fragment>
								))}
							</div>

							{/* FILES */}
							{!!order?.attachments?.length && (
								<div className={styles.itemsWrapper}>
									<div className={styles.label}>Documents</div>

									<div className={styles.itemsContent}>
										{order?.attachments?.map((file, index) => (
											<Fragment key={index}>
												<div className={styles.document}>
													<div className={styles.value}>
														<Icon type={getFileCategory(file.filename)} size={16} />
													</div>

													<div className={cn(styles.value, '!truncate')} title={file.filename}>
														{file.filename}
													</div>

													<button
														type="button"
														className={styles.fileDownload}
														onClick={() => downloadFileByUrl(file.path, file.filename)}
														title="Download"
													>
														<Icon type="download_file" size={16} />
													</button>
												</div>

												<div className={styles.hr} />
											</Fragment>
										))}
									</div>
								</div>
							)}
						</div>

						{(!!order?.stackable || !!order?.manipulator_needed) && (
							<>
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
							</>
						)}

						<div className={styles.routeItem}>
							<div className={styles.routeWrapper}>
								<div className={cn(styles.route, { [styles.delivered]: isDelivered })} />
							</div>

							<div className={styles.items}>
								<div className={styles.item}>
									<div className={styles.label}>Pickup</div>
									<div className={styles.value}>{order?.address.from || EMPTY_STRING}</div>
								</div>

								{!isDelivered && (
									<div className={styles.row}>
										<div className={styles.item}>
											<div className={styles.label}>Loading ready</div>
											<div className={styles.value}>
												{order?.pickup_request_date || EMPTY_STRING}
											</div>
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
								)}

								<div className={styles.hr} />

								<div className={styles.item}>
									<div className={styles.label}>Delivery</div>
									<div className={styles.value}>{order?.address.to || EMPTY_STRING}</div>
								</div>

								{!isDelivered && (
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
								)}
							</div>
						</div>

						<div className={styles.hr} />

						<div className={styles.item}>
							<div className={styles.label}>Comments</div>
							<div className={styles.comments}>{order?.comment || EMPTY_STRING}</div>
						</div>

						<div className={styles.hr} />

						<PriceBlock />
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
							boundsOptions={
								showId
									? {
											paddingTopLeft: [50, 120],
											paddingBottomRight: [50, 220],
										}
									: order.status < OrderStatusEnum.ACCEPTED
										? {
												paddingTopLeft: [50, 50],
												paddingBottomRight: [50, 220],
											}
										: { padding: [50, 50] }
							}
							style={{
								width: '100%',
								height: '100%',
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
							<div className={styles.value}>{orderReferenceDisplay}</div>
						</div>
					)}

					{showInfo && (
						<div className={styles.info}>
							<div className={cn(styles.top, 'w-full')}>
								<PriceBlock />
							</div>

							<div className={styles.hr} />

							<div className={cn(styles.bottom, { [styles.isOffered]: isOffered })}>
								<div className={styles.bottomLeft}>
									{isCarrier && !!order?.sender?.first_name && !!order?.sender?.last_name && (
										<>
											<div
												className={cn({
													[styles.avatar]: isCarrier,
												})}
											>
												{order.sender.first_name.charAt(0)}
												{order.sender.last_name?.charAt(0)}
											</div>

											<div className={styles.text}>
												<span className={styles.title}>
													{order?.sender?.first_name} {order?.sender?.last_name}
												</span>
												{/* <span className={styles.subtitle}>Delivery time</span> */}
											</div>
										</>
									)}

									{isOffered && (
										<>
											<div className={styles.icon}>
												<Icon type="clock_1_light" size={30} />
											</div>
											<div className={styles.text}>
												<div className={styles.subtitle}>Delivery time</div>
												<div className={styles.title}>~ 48 hours</div>
											</div>
										</>
									)}
								</div>

								<div className={styles.bottomRight}>
									{isCarrier && isRequest ? (
										<form
											method="POST"
											className="contents"
											action={carrierConfirmRequestUrl(order.id)}
										>
											<Button
												type="button"
												className="w-full"
												variant="transparent"
												onClick={() => setModalId('declineCarrier')}
											>
												Decline
											</Button>

											<Button type="submit" name="action" value="confirm" className="w-full">
												Confirm
											</Button>
										</form>
									) : !isCarrier ? (
										<>
											{order.status <= OrderStatusEnum.OFFERED && abandonCsrfToken && (
												<form
													method="POST"
													className="contents"
													action={userAbandonOrderUrl(order.id)}
												>
													<input type="hidden" name="_token" value={abandonCsrfToken} />
													<Button type="submit" className="w-full" variant="outline">
														Cancel
													</Button>
												</form>
											)}
											{order.status === OrderStatusEnum.OFFERED && csrfToken && (
												<form
													method="POST"
													className="contents"
													action={userConfirmOrderUrl(order.id)}
												>
													<input type="hidden" name="_token" value={csrfToken} />
													<Button type="submit" className="w-full">
														Confirm
													</Button>
												</form>
											)}
										</>
									) : null}
								</div>
							</div>
						</div>
					)}
				</div>

				{showStatus && (
					<StatusOrder
						order={order}
						setModalId={setModalId}
						isCarrier={isCarrier}
						csrfToken={updateStatusCsrfToken}
					/>
				)}
			</div>

			{/* CONFIRM */}
			{!isCarrier && (
				<Modal
					isOpen={modalId === 'confirmSender'}
					onClose={() => setModalId(undefined)}
					maxWidth="400px"
				>
					<ConfirmModal
						id={order.id}
						from={order.address.from}
						to={order.address.to}
						onClose={() => setModalId(undefined)}
						email={'example@email.com'}
					/>
				</Modal>
			)}

			{/* CANCEL */}
			<Modal isOpen={modalId === 'cancel'} onClose={() => setModalId(undefined)} maxWidth="400px">
				<CancelModal
					id={order.id}
					from={order.address.from}
					to={order.address.to}
					isCarrier={isCarrier}
					actionUrl={isCarrier ? carrierCancelOrderUrl(order.id) : userCancelOrderUrl(order.id)}
					csrfToken={cancelCsrfToken}
				/>
			</Modal>

			{/* RATE */}
			<Modal isOpen={modalId === 'rate'} onClose={() => setModalId(undefined)} maxWidth="400px">
				<RateModal id={order.id} />
			</Modal>

			{/* DECLINE CARRIER */}
			{isCarrier && (
				<Modal
					isOpen={modalId === 'declineCarrier'}
					onClose={() => setModalId(undefined)}
					maxWidth="400px"
				>
					<DeclineModal
						id={order.id}
						from={order.address.from}
						to={order.address.to}
						actionUrl={carrierDeclineRequestUrl(order.id)}
					/>
				</Modal>
			)}
		</>
	)
}
