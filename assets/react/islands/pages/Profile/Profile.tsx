import type { FC } from 'react'

import { CircleChart } from '@ui/CircleChart/CircleChart'
import { cn } from '@utils/cn'

import styles from './Profile.module.css'

interface ProfilePageProps {
	user: {
		image: string
		name: string
		company: string
		accountType: 'sender' | 'carrier'
		requisites: string
		reg: string
		address: string
		contacts: string
		email: string
	}
	orders: {
		// Sender
		total?: number
		completed?: number
		cancelled?: number
		inProgress?: number
		// Carrier
		successfulDeliveries?: number
		applyRate?: number
		cancelledBySender?: number
	}
}

export const ProfilePage: FC<ProfilePageProps> = ({ user, orders }) => {
	return (
		<div className={cn('tw-container', styles.page)}>
			<h1 className={styles.title}>Profile</h1>

			<div className={cn(styles.content, { ['!grid-cols-3']: user.accountType === 'carrier' })}>
				<div className={styles.left}>
					<div className={styles.top}>
						<div
							className={styles.avatar}
							style={
								!user?.image?.length
									? {}
									: {
											background: `url(${user.image}) no-repeat center center/cover `,
										}
							}
						>
							{!user?.image?.length && (
								<>
									{user?.name.split(' ')[0].charAt(0)}
									{user?.name.split(' ')[1].charAt(0)}
								</>
							)}
						</div>

						<div className={styles.nameWrapper}>
							<div className={styles.name}>{user.name}</div>
							<div className={styles.company}>{user.company}</div>
						</div>
					</div>

					<div className={styles.bottom}>
						<div className={styles.item}>
							<div className={styles.label}>Account type</div>
							<div className={styles.value}>
								{`${user.accountType.charAt(0).toUpperCase()}${user.accountType.slice(1)}`}
							</div>
						</div>

						<div className={styles.hr} />

						<div className={styles.item}>
							<div className={styles.label}>Requisites</div>
							<div className={styles.value}>{user.requisites}</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>Reg</div>
							<div className={styles.value}>{user.reg}</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>Address</div>
							<div className={styles.value}>{user.address}</div>
						</div>

						<div className={styles.hr} />

						<div className={styles.item}>
							<div className={styles.label}>Contacts</div>
							<div className={styles.value}>{user.contacts}</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>E-mail</div>
							<div className={styles.value}>{user.email}</div>
						</div>
					</div>
				</div>

				{user.accountType === 'sender' && (
					<div className={cn(styles.right, styles.sender)}>
						<div className={styles.card}>
							<div className={styles.title}>My orders</div>
							<div className={styles.row}>
								<div className={styles.item}>
									<div className={styles.value}>{orders.total}</div>
									<div className={styles.label}>Total orders</div>
								</div>
								<div className={styles.item}>
									<div className={styles.value}>{orders.completed}</div>
									<div className={styles.label}>Completed</div>
								</div>
							</div>

							<div className={styles.row}>
								<div className={styles.item}>
									<div className={styles.value}>{orders.cancelled}</div>
									<div className={styles.label}>Cancelled</div>
								</div>
								<div className={styles.item}>
									<div className={styles.value}>{orders.inProgress}</div>
									<div className={styles.label}>In progress</div>
								</div>
							</div>
						</div>
					</div>
				)}

				{user.accountType === 'carrier' && (
					<>
						<div className={styles.centerWrapper}>
							<div className={cn(styles.center, styles.top)}>
								<div className={styles.title}>My stats</div>

								<div className={styles.statsWrapper}>
									<div>
										<CircleChart
											size={120}
											percent={orders?.successfulDeliveries || 0}
											title={`${orders?.successfulDeliveries}%`}
											subtitle="Successful deliveries"
										/>
									</div>

									<div>
										<CircleChart
											size={150}
											percent={orders?.applyRate || 0}
											title={`${orders?.applyRate}%`}
											subtitle="Apply rate"
										/>
									</div>
								</div>
							</div>

							<div className={cn(styles.center, styles.bottom)}>
								<div className={styles.title}>My orders</div>

								<div className={styles.orders}>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelledBySender}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelledBySender}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelledBySender}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelledBySender}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelledBySender}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelledBySender}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
								</div>
							</div>
						</div>

						<div className={cn(styles.right, styles.carrier)}>
							<div className={styles.title}>My tariffs</div>
						</div>
					</>
				)}
			</div>
		</div>
	)
}
