import { type FC, useState } from 'react'

import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'

import styles from './Requests.module.css'

import { InputButton } from './components/InputButton/InputButton'
import { ModalContent } from './components/ModalContent/ModalContent'

interface RequestsPageProps {
	orders?: { name: string; count: number; route: { from: string; to: string } }[]
	latestRoutes?: { from: string; to: string }[]
}

export type CalculateModalType = 'what' | 'where' | 'when' | 'calculate' | undefined

export const RequestsPage: FC<RequestsPageProps> = ({ orders, latestRoutes }) => {
	const [activeButton, setActiveButton] = useState<CalculateModalType>()

	return (
		<>
			<div className={cn('tw-container', styles.page)}>
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
				<div className={cn(styles.ordersWrapper, { ['!px-0']: !!orders?.length })}>
					{!orders?.length ? (
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
								{orders?.map((order, index) => (
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
