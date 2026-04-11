import { type FC, useCallback, useRef, useState } from 'react'

import { EMAIL, PHONE, PUBLIC_SUPPORT_CONTACT_URL, Routes } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Popover } from '@radix-ui/themes'
import { Icon } from '@ui/Icon/Icon'
import { cn } from '@utils/cn'

import styles from './Info.module.css'

interface InfoProps {
	orders?: {
		id: string
		name: string
		status: 'In Transit' | 'Delivered'
		hours: number
	}[]
	device?: DeviceType
}
export const Info: FC<InfoProps> = (props) => {
	const [currentOrder, setCurrentOrder] = useState(0)
	const [supportEmail, setSupportEmail] = useState(EMAIL)
	const [supportPhone, setSupportPhone] = useState(PHONE)
	const supportContactLoadedRef = useRef(false)

	const { orders, device } = props

	const { isMobile } = useDevice(device)

	const loadSupportContact = useCallback(() => {
		if (supportContactLoadedRef.current) {
			return
		}
		void fetch(PUBLIC_SUPPORT_CONTACT_URL, { credentials: 'same-origin' })
			.then((res) => (res.ok ? res.json() : null))
			.then((data: { email?: string | null; phone?: string | null } | null) => {
				if (!data) {
					return
				}
				supportContactLoadedRef.current = true
				if (data.email?.trim()) {
					setSupportEmail(data.email.trim())
				}
				if (data.phone?.trim()) {
					setSupportPhone(data.phone.trim())
				}
			})
			.catch(() => {})
	}, [])

	if (isMobile) return null

	const telHref = supportPhone.replace(/\s/g, '')

	return (
		<>
			<Popover.Root onOpenChange={(open) => open && loadSupportContact()}>
				<Popover.Trigger>
					<div className={styles.info}>?</div>
				</Popover.Trigger>
				<Popover.Content width="390px" height="220px" className={styles.infoPopover}>
					<div className={styles.img}>{/* <img src={user} alt="" /> */}</div>

					<div className={styles.content}>
						<h3 className={styles.title}>Need help?</h3>

						<div>
							<a className={styles.link} href={`tel:${telHref}`}>
								{supportPhone}
							</a>
							<a className={styles.link} href={`mailto:${supportEmail}`}>
								{supportEmail}
							</a>
						</div>
					</div>

					<div className={cn(styles.info, '!absolute !cursor-default !bottom-3 !right-3')}>?</div>
				</Popover.Content>
			</Popover.Root>

			{/* {!!orders?.length && (
				<Popover.Root>
					<Popover.Trigger>
						<div className={styles.orders}>
							<span /> {orders.length} ongoing orders
						</div>
					</Popover.Trigger>
					<Popover.Content
						width="240px"
						height="160px"
						className={styles.ordersPopover}
						style={{ outlineColor: 'red' }}
					>
						<div
							className={styles.leftBtn}
							onClick={() => setCurrentOrder((v) => (v === 0 ? orders.length - 1 : v - 1))}
						/>
						<div
							className={styles.rightBtn}
							onClick={() => setCurrentOrder((v) => (v === orders.length - 1 ? 0 : v + 1))}
						/>

						<div className={styles.content}>
							{orders[currentOrder] && (
								<div className={styles.header} key={currentOrder}>
									<div
										className={cn(styles.leftBlock, {
											['!items-center']: orders[currentOrder].status === 'Delivered',
										})}
									>
										{orders[currentOrder].status === 'In Transit' && (
											<div className={styles.circle}>
												<span className="text-white font-medium text-xl">
													{orders[currentOrder].hours}h
												</span>
												<span className="text-gray-400 font-medium text-xs">left</span>
											</div>
										)}
										{orders[currentOrder].status === 'Delivered' && <Icon type="green_check" />}
									</div>

									<div className={styles.rightBlock}>
										<div className={styles.wrapper}>
											<span className={styles.title}>{orders[currentOrder].name}</span>
											<span className={styles.subtitle}>{orders[currentOrder].status}</span>
										</div>

										<a
											//
											href={`${isCarrier ? Routes.CARRIER_ORDERS : Routes.USER_ORDERS}/${orders[currentOrder].id}`}
											className={styles.link}
										>
											View details
										</a>
									</div>
								</div>
							)}

							<div className={styles.footer}>
								{orders.map((_, index) => (
									<span
										className={cn({ ['!bg-white']: index === currentOrder })}
										key={index}
										onClick={() => setCurrentOrder(index)}
									/>
								))}
							</div>
						</div>
					</Popover.Content>
				</Popover.Root>
			)} */}
		</>
	)
}
