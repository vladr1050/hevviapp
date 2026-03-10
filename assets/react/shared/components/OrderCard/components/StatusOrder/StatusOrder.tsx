import { type FC, ReactNode, SetStateAction, useState } from 'react'

import { FormActions, OrderStatusEnum, OrderType } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { CircleChart } from '@ui/CircleChart/CircleChart'
import { Icon } from '@ui/Icon/Icon'
import { IconNameType } from '@ui/Icon/Icon.types'
import { cn } from '@utils/cn'

import styles from './StatusOrder.module.css'

// @ts-ignore
import awaitingPaymentGif from './images/awaitingPayment.gif'
// @ts-ignore
import awaitingPickupGif from './images/awaitingPickup.gif'
// @ts-ignore
import carrierMatchedGig from './images/carrierMatched.gif'
// @ts-ignore
import inTransitGif from './images/inTransit.gif'

interface StatusOrderProps {
	isCarrier?: boolean
	order: OrderType
	setModalId: (value: SetStateAction<any>) => void
}

export const StatusOrder: FC<StatusOrderProps> = ({ isCarrier, order, setModalId }) => {
	const [valueForm, setValueForm] = useState<'PICKUP_DONE' | 'DELIVERED'>()

	return (
		<div
			className={cn(styles.status, {
				[styles.sender]: !isCarrier,
				[styles.carrier]: isCarrier,
			})}
		>
			<div className={styles.title}>Status</div>

			{!isCarrier && (
				<div className={styles.statusWrapper}>
					<div className={styles.item}>
						Awaiting Payment
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.ACCEPTED && (
							<div className={styles.active}>
								{order.status <= OrderStatusEnum.INVOICED
									? 'Awaiting Payment'
									: 'Payment successful'}
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.ACCEPTED,
									})}
								>
									{order.status > OrderStatusEnum.INVOICED ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img
											alt=""
											src={awaitingPaymentGif} // carrierMatchedGig
											style={{ width: '48px', height: '48px' }}
										/>
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						Carrier matched
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.PAID && (
							<div className={styles.active}>
								Carrier matched
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.PAID,
									})}
								>
									{order.status > OrderStatusEnum.PAID ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img alt="" src={carrierMatchedGig} style={{ width: '48px', height: '48px' }} />
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						Awaiting pickup
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.ASSIGNED && (
							<div className={styles.active}>
								Awaiting pickup
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.AWAITING_PICKUP,
									})}
								>
									{order.status > OrderStatusEnum.AWAITING_PICKUP ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img alt="" src={awaitingPickupGif} style={{ width: '48px', height: '48px' }} />
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						In transit
						<div className={styles.dot} />
						{order.status >= OrderStatusEnum.PICKUP_DONE && (
							<div className={styles.active}>
								In transit
								<div
									className={cn(styles.icon, {
										[styles.activeIcon]: order.status > OrderStatusEnum.IN_TRANSIT,
									})}
								>
									{order.status > OrderStatusEnum.IN_TRANSIT ? (
										<Icon type="check_circle_1" size={20} />
									) : (
										<img alt="" src={inTransitGif} style={{ width: '48px', height: '48px' }} />
									)}
								</div>
							</div>
						)}
					</div>

					<div className={styles.line} />

					<div className={styles.item}>
						Delivery
						<Icon type="mark_map" size={24} className="!translate-x-0.5" />
						{order.status === OrderStatusEnum.DELIVERED && (
							<div className={styles.active}>
								Delivered
								<div className={cn(styles.icon, styles.activeIcon)}>
									<Icon type="check_circle_1" size={20} />
								</div>
							</div>
						)}
					</div>
				</div>
			)}

			{isCarrier && (
				<div className={styles.statusWrapper}>
					<div className={styles.top}>
						<CircleChart size={150} percent={75} title="15:00:00h" subtitle="left" countdown />
					</div>

					<div className={styles.bottom}>
						<ItemCarrier
							iconType="up_box"
							label={
								<>
									Pickup
									<br />
									done
								</>
							}
							checked={
								order.status > OrderStatusEnum.AWAITING_PICKUP || valueForm === 'PICKUP_DONE'
							}
							isActive={order.status > OrderStatusEnum.AWAITING_PICKUP}
							isWaiting={order.status === OrderStatusEnum.AWAITING_PICKUP}
							showInfo={order.status === OrderStatusEnum.AWAITING_PICKUP}
							infoText="Awaiting pickup"
							onClick={() => setValueForm('PICKUP_DONE')}
						/>

						<div
							className={cn(styles.line, {
								[styles.big]: order.status === OrderStatusEnum.IN_TRANSIT,
							})}
						/>

						<ItemCarrier
							iconType="vehicle_right"
							label={
								<>
									Delivered to
									<br />
									[destination]
								</>
							}
							checked={order.status > OrderStatusEnum.IN_TRANSIT || valueForm === 'DELIVERED'}
							isActive={order.status > OrderStatusEnum.IN_TRANSIT}
							isWaiting={order.status === OrderStatusEnum.IN_TRANSIT}
							showInfo={order.status === OrderStatusEnum.IN_TRANSIT}
							infoText="In transit"
							onClick={() => setValueForm('DELIVERED')}
						/>

						<div
							className={cn(styles.line, {
								[styles.big]: order.status === OrderStatusEnum.DELIVERED,
							})}
						/>

						<ItemCarrier
							iconType="check_circle_1"
							label={
								<>
									Approved
									<br />
									by Sender
								</>
							}
							checked={order.status > OrderStatusEnum.DELIVERED}
							isActive={order.status > OrderStatusEnum.DELIVERED}
							hideCheckbox
							isWaiting={order.status === OrderStatusEnum.DELIVERED}
							showInfo={order.status === OrderStatusEnum.DELIVERED}
							infoText="Pending approval"
						/>
					</div>
				</div>
			)}

			{!isCarrier && (
				<>
					<div>
						{/* {order.status < OrderStatusEnum.PICKUP_DONE && (
						<Button
						type="button"
						className="!w-full"
						variant="transparent"
						onClick={() => setModalId('cancel')}
						>
							Cancel order
						</Button>
					)} */}
					</div>

					{(order.status === OrderStatusEnum.PICKUP_DONE ||
						order.status === OrderStatusEnum.IN_TRANSIT) && <div />}

					{order.status >= OrderStatusEnum.DELIVERED && (
						<Button type="button" className="!w-full" onClick={() => setModalId('rate')}>
							Rate delivery
						</Button>
					)}
				</>
			)}

			{isCarrier && (
				<>
					<div className={styles.footer}>
						{!!valueForm && (
							<form method="POST" action={FormActions.UPDATE_STATUS} className={styles.button}>
								<Button
									type="submit"
									variant="outline"
									name="action"
									value={valueForm}
									className="w-full"
								>
									Update status
								</Button>
							</form>
						)}

						{order.status < OrderStatusEnum.PICKUP_DONE && (
							<Button
								type="button"
								// className="!w-full"
								variant="transparent"
								onClick={() => setModalId('cancel')}
								className={styles.button}
							>
								Cancel order
							</Button>
						)}

						{order.status === OrderStatusEnum.DELIVERED && (
							<Button
								type="button"
								className={styles.button}
								// className="!w-full"
								onClick={() => setModalId('rate')}
							>
								Rate delivery
							</Button>
						)}
					</div>
				</>
			)}
		</div>
	)
}

//
//
//
//
//

type ItemCarrierProps = {
	showInfo?: boolean
	isWaiting?: boolean
	isActive?: boolean
	checked?: boolean
	infoText?: string
	iconType: IconNameType
	label: ReactNode
	hideCheckbox?: boolean
	onClick?: () => void
}

const ItemCarrier: FC<ItemCarrierProps> = ({
	showInfo,
	isWaiting,
	isActive,
	iconType,
	label,
	checked,
	infoText,
	hideCheckbox,
	onClick,
}) => {
	return (
		<div
			className={cn(styles.itemWrapper, {
				['!cursor-pointer']: isWaiting && onClick,
			})}
			onClick={isWaiting ? onClick : undefined}
		>
			{showInfo && (
				<div className={styles.info}>
					<div className={styles.dot} />
					{infoText}
				</div>
			)}

			<div
				className={cn(styles.item, {
					[styles.waiting]: isWaiting,
					[styles.active]: isActive,
				})}
			>
				{hideCheckbox && <div />}
				{!hideCheckbox && (
					<Checkbox value={checked} disabledWithoutCss disabled={!isWaiting && !isActive} />
				)}

				<span>{label}</span>

				<div className={styles.icon}>
					<Icon type={iconType} size={25} />
				</div>
			</div>
		</div>
	)
}
