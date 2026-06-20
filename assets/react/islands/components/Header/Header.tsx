import type { FC } from 'react'

import { Routes } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { useLocation } from '@hooks/useLocation'
import { Popover } from '@radix-ui/themes'
import { Icon } from '@ui/Icon/Icon'
import { Tabs } from '@ui/Tabs/Tabs'
import { cn } from '@utils/cn'

import logo from '../../pages/Landing/images/logo.png'
import styles from './Header.module.css'

interface HeaderProps {
	user?: {
		first_name?: string
		last_name?: string
		company_name?: string
	}
	isCarrier?: boolean
	device?: DeviceType
}

/** Set to `false` to hide the auth link in the header on login/registration pages. */
const SHOW_AUTH_LINK_IN_HEADER = true

export const Header: FC<HeaderProps> = ({ user, isCarrier, device }) => {
	const { push, pathname } = useLocation()

	const { isMobile } = useDevice(device)

	const requestsTab = isCarrier ? Routes.CARRIER_REQUESTS : Routes.USER_REQUESTS
	const ordersTab = isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS

	const tabsDefaultValue =
		pathname.includes(requestsTab) || pathname === Routes.HOME
			? requestsTab
			: pathname.includes(ordersTab)
				? ordersTab
				: requestsTab

	return (
		<div className={styles.wrapper}>
			<div className={cn('tw-container', styles.header, { [styles.mobile]: isMobile })}>
				<div className={styles.left}>
					<a className={styles.logo} href={Routes.HOME} aria-label="Hevvi home">
						<img src={logo} alt="Hevvi" width={71} height={19} className={styles.logoImage} />
						<span className={styles.betaBadge}>beta</span>
					</a>
				</div>

				{!isMobile && (
					<>
						<div className={styles.center}>
							{typeof user !== 'undefined' && (
								<Tabs
									items={[
										{
											label: 'Requests',
											value: requestsTab,
										},
										{
											label: 'Orders',
											value: ordersTab,
										},
									]}
									defaultValue={tabsDefaultValue}
									onChange={(v) => push(v)}
								/>
							)}
						</div>

						<div className={styles.right}>
							{typeof user === 'undefined' ? (
								<div>
									{SHOW_AUTH_LINK_IN_HEADER && (
										<a
											className={styles.link}
											href={
												pathname === Routes.REGISTRATION
													? Routes.LOGIN
													: Routes.REGISTRATION
											}
										>
											{pathname === Routes.REGISTRATION ? 'Login' : 'Register'}
										</a>
									)}
								</div>
							) : (
								<Popover.Root>
									<Popover.Trigger>
										<div
											className={cn(styles.profileWrapper, {
												[styles.active]: pathname.includes(
													isCarrier ? Routes.CARRIER_PROFILE : Routes.USER_PROFILE
												),
											})}
										>
											<div className={styles.profile}>
												<div className={styles.avatar}>
													{user?.first_name?.charAt(0)}
													{user?.last_name?.charAt(0)}
												</div>

												<div>
													<div className={styles.name}>
														{user.first_name} {user.last_name}
													</div>

													{!!user?.company_name?.length && (
														<div className={styles.company}>{user.company_name}</div>
													)}
												</div>
											</div>
										</div>
									</Popover.Trigger>
									<Popover.Content width="390px" height="220px" className={styles.popover}>
										<a
											className={styles.link}
											href={isCarrier ? Routes.CARRIER_PROFILE : Routes.USER_PROFILE}
										>
											<div className={styles.iconWrapper}>
												<Icon
													type="profile"
													size={24}
													className={cn(styles.icon, '-translate-x-[2px]')}
												/>
											</div>
											Profile
										</a>

										<a className={styles.link} href={Routes.LOGOUT}>
											<div className={styles.iconWrapper}>
												<Icon type="logout" className={styles.icon} />
											</div>
											Logout
										</a>
									</Popover.Content>
								</Popover.Root>
							)}
						</div>
					</>
				)}
			</div>
		</div>
	)
}
