import { type FC, useCallback, useRef, useState } from 'react'

import { EMAIL, PHONE, PUBLIC_SUPPORT_CONTACT_URL } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
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

const HOVER_CLOSE_DELAY_MS = 120

export const Info: FC<InfoProps> = (props) => {
	const [currentOrder, setCurrentOrder] = useState(0)
	const [isOpen, setIsOpen] = useState(false)
	const [supportPhone, setSupportPhone] = useState(PHONE)
	const supportContactLoadedRef = useRef(false)
	const closeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

	const { device } = props

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
				if (data.phone?.trim()) {
					setSupportPhone(data.phone.trim())
				}
			})
			.catch(() => {})
	}, [])

	const openSupport = useCallback(() => {
		if (closeTimerRef.current) {
			clearTimeout(closeTimerRef.current)
			closeTimerRef.current = null
		}
		setIsOpen(true)
		loadSupportContact()
	}, [loadSupportContact])

	const scheduleClose = useCallback(() => {
		if (closeTimerRef.current) {
			clearTimeout(closeTimerRef.current)
		}
		closeTimerRef.current = setTimeout(() => {
			setIsOpen(false)
			closeTimerRef.current = null
		}, HOVER_CLOSE_DELAY_MS)
	}, [])

	if (isMobile) return null

	const telHref = supportPhone.replace(/\s/g, '')
	const hoverHandlers = {
		onMouseEnter: openSupport,
		onMouseLeave: scheduleClose,
	}

	return (
		<div
			className={cn(styles.supportRoot, { [styles.supportRootOpen]: isOpen })}
			{...hoverHandlers}
		>
			{isOpen && (
				<div className={styles.infoPopover}>
					<div className={styles.avatar} aria-hidden />

					<div className={styles.body}>
						<h3 className={styles.title}>Need help?</h3>

						<div className={styles.contacts}>
							<a className={styles.contact} href={`tel:${telHref}`}>
								{supportPhone}
							</a>
							<a className={styles.contact} href={`mailto:${EMAIL}`}>
								{EMAIL}
							</a>
						</div>
					</div>

					<div className={cn(styles.infoIcon, styles.infoIconInPopover)}>?</div>
				</div>
			)}

			{!isOpen && <div className={styles.infoIcon}>?</div>}
		</div>
	)
}
