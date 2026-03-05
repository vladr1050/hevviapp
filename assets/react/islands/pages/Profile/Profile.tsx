import type { FC } from 'react'

import { AccountType, EMPTY_STRING } from '@config/constants'
import { CircleChart } from '@ui/CircleChart/CircleChart'
import { cn } from '@utils/cn'

import styles from './Profile.module.css'

interface ProfilePageProps {
	title: string
	accountType: AccountType
	user: {
		company_address?: string
		company_name?: string
		company_registration_number?: string
		email?: string
		first_name?: string
		last_name?: string
		phone?: string
	}
	orders: {
		cancelled: number
		delivered: number
		in_progress: number
		total: number
	}
}

export const ProfilePage: FC<ProfilePageProps> = (props) => {
	const { title, accountType, user, orders } = props
	console.log(props)

	return (
		<div className={cn('tw-container', styles.page)}>
			<h1 className={styles.title}>{title}</h1>

			<div className={cn(styles.content, { ['!grid-cols-3']: accountType === 'Carrier' })}>
				<div className={styles.left}>
					<div className={styles.top}>
						<div className={styles.avatar}>
							{user?.first_name?.charAt(0)}
							{user?.last_name?.charAt(0)}
						</div>

						<div className={styles.nameWrapper}>
							<div className={styles.name}>
								{user?.first_name} {user?.last_name}
							</div>
						</div>
					</div>

					<div className={styles.bottom}>
						<div className={styles.item}>
							<div className={styles.label}>Account type</div>
							<div className={styles.value}>
								{`${accountType.charAt(0).toUpperCase()}${accountType.slice(1)}`}
							</div>
						</div>

						<div className={styles.hr} />

						{!!user?.company_name ||
							!!user?.company_registration_number ||
							(!!user?.company_address && (
								<>
									{!!user?.company_name && (
										<div className={styles.item}>
											<div className={styles.label}>Requisites</div>
											<div className={styles.value}>{user.company_name}</div>
										</div>
									)}

									{!!user?.company_registration_number && (
										<div className={styles.item}>
											<div className={styles.label}>Reg</div>
											<div className={styles.value}>{user.company_registration_number}</div>
										</div>
									)}

									{!!user?.company_address && (
										<div className={styles.item}>
											<div className={styles.label}>Address</div>
											<div className={styles.value}>{user.company_address}</div>
										</div>
									)}

									<div className={styles.hr} />
								</>
							))}

						<div className={styles.item}>
							<div className={styles.label}>Contacts</div>
							<div className={styles.value}>{user?.phone || EMPTY_STRING}</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>E-mail</div>
							<div className={styles.value}>{user?.email || EMPTY_STRING}</div>
						</div>
					</div>
				</div>

				{accountType === 'Sender' && (
					<div className={cn(styles.right, styles.sender)}>
						<div className={styles.card}>
							<div className={styles.title}>My orders</div>
							<div className={styles.row}>
								<div className={styles.item}>
									<div className={styles.value}>{orders.total || 0}</div>
									<div className={styles.label}>Total orders</div>
								</div>
								<div className={styles.item}>
									<div className={styles.value}>{orders.delivered || 0}</div>
									<div className={styles.label}>Completed</div>
								</div>
							</div>

							<div className={styles.row}>
								<div className={styles.item}>
									<div className={styles.value}>{orders.cancelled || 0}</div>
									<div className={styles.label}>Cancelled</div>
								</div>
								<div className={styles.item}>
									<div className={styles.value}>{orders.in_progress || 0}</div>
									<div className={styles.label}>In progress</div>
								</div>
							</div>
						</div>
					</div>
				)}

				{accountType === 'Carrier' && (
					<>
						<div className={styles.centerWrapper}>
							<div className={cn(styles.center, styles.top)}>
								<div className={styles.title}>My stats</div>

								<div className={styles.statsWrapper}>
									<div>
										<CircleChart
											size={120}
											percent={97}
											title={`${97}%`}
											subtitle="Successful deliveries"
										/>
									</div>

									<div>
										<CircleChart size={150} percent={73} title={`${73}%`} subtitle="Apply rate" />
									</div>
								</div>
							</div>

							<div className={cn(styles.center, styles.bottom)}>
								<div className={styles.title}>My orders</div>

								<div className={styles.orders}>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
										<div className={styles.label}>Cancelled by sender</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
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
