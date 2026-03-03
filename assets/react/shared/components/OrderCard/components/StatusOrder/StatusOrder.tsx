import { type FC, ReactNode, SetStateAction, useState } from 'react'

import { FormActions, OrderStatusId, OrderType, StatusCarrierId } from '@config/constants'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { CircleChart } from '@ui/CircleChart/CircleChart'
import { Icon } from '@ui/Icon/Icon'
import { IconNameType } from '@ui/Icon/Icon.types'
import { cn } from '@utils/cn'

import styles from './StatusOrder.module.css'

interface StatusOrderProps {
	accountType: 'sender' | 'carrier'
	order: OrderType
	setModalId: (value: SetStateAction<any>) => void
}

export const StatusOrder: FC<StatusOrderProps> = ({ accountType, order, setModalId }) => {
	const statusCarrierId = StatusCarrierId['awaitingPickup'] as number
	// const statusCarrierId = StatusCarrierId['inTransit'] as number
	// const statusCarrierId = StatusCarrierId['pendingApproval'] as number
	// const statusCarrierId = StatusCarrierId['approvedBySender'] as number

	const [valueForm, setValueForm] = useState<'PICKUP_DONE' | 'DELIVERED'>()

	// @ts-ignore
	const statusId = OrderStatusId[order?.status]

	return (
		<div
			className={cn(styles.status, {
				[styles.sender]: accountType === 'sender',
				[styles.carrier]: accountType === 'carrier',
			})}
		>
			<div className={styles.title}>Status</div>

			{accountType === 'sender' && (
				<div className={styles.statusWrapper}>
					<div className={styles.item}>
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

					<div className={styles.item}>
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

					<div className={styles.item}>
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

					<div className={styles.item}>
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

					<div className={styles.item}>
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
			)}

			{accountType === 'carrier' && (
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
							checked={statusCarrierId > 0 || valueForm === 'PICKUP_DONE'}
							isActive={statusCarrierId > 0}
							isWaiting={statusCarrierId === 0}
							showInfo={statusCarrierId === 0}
							infoText="Awaiting pickup"
							onClick={() => setValueForm('PICKUP_DONE')}
						/>

						<div className={cn(styles.line, { [styles.big]: statusCarrierId === 1 })} />

						<ItemCarrier
							iconType="vehicle_right"
							label={
								<>
									Delivered to
									<br />
									[destination]
								</>
							}
							checked={statusCarrierId > 1 || valueForm === 'DELIVERED'}
							isActive={statusCarrierId > 1}
							isWaiting={statusCarrierId === 1}
							showInfo={statusCarrierId === 1}
							infoText="In transit"
							onClick={() => setValueForm('DELIVERED')}
						/>

						<div className={cn(styles.line, { [styles.big]: statusCarrierId === 2 })} />

						<ItemCarrier
							iconType="check_circle_1"
							label={
								<>
									Approved
									<br />
									by Sender
								</>
							}
							checked={statusCarrierId > 2}
							isActive={statusCarrierId > 2}
							hideCheckbox
							isWaiting={statusCarrierId === 2}
							showInfo={statusCarrierId === 2}
							infoText="Pending approval"
						/>
					</div>
					{/*  */}
					{/*  */}
				</div>
			)}

			{accountType === 'sender' && (
				<>
					{statusId < 4 && (
						<Button
							type="button"
							className="!w-full"
							variant="transparent"
							onClick={() => setModalId('cancel')}
						>
							Cancel order
						</Button>
					)}

					{(statusId === 4 || statusId === 5) && <div />}

					{statusId === 6 && (
						<Button type="button" className="!w-full" onClick={() => setModalId('rate')}>
							Rate delivery
						</Button>
					)}
				</>
			)}

			{accountType === 'carrier' && (
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

						{statusCarrierId < 2 && statusId < 4 && (
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

						{(statusCarrierId === 3 || statusId === 6) && (
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
