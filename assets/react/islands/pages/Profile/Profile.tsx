import type { FC } from 'react'
import { useEffect, useState } from 'react'

import { apiFetchCurrentTerms, type TermsCurrentResponse } from '@api/termsApi'
import { AccountType, EMPTY_STRING } from '@config/constants'
import { useAuth } from '@hooks/useAuth'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { CircleChart } from '@ui/CircleChart/CircleChart'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'

import styles from './Profile.module.css'

import { MobilePage } from '../MobilePage/MobilePage'

interface ProfilePageProps {
	title: string
	accountType: AccountType
	isCarrier?: boolean
	device?: DeviceType
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
		delivery_percent?: number
		approval_percent?: number
	}
}

export const ProfilePage: FC<ProfilePageProps> = (props) => {
	const { title, accountType, isCarrier, user, orders, device } = props
	const [termsOpen, setTermsOpen] = useState(false)
	const [termsLoading, setTermsLoading] = useState(false)
	const [termsError, setTermsError] = useState<string | null>(null)
	const [termsData, setTermsData] = useState<TermsCurrentResponse | null>(null)

	const { getValidAccessToken } = useAuth()

	const { isMobile } = useDevice(device)

	useEffect(() => {
		if (!termsOpen) {
			return
		}

		let cancelled = false

		;(async () => {
			setTermsLoading(true)
			setTermsError(null)

			const token = await getValidAccessToken()
			if (!token) {
				if (!cancelled) {
					setTermsLoading(false)
					setTermsError('Not signed in or session expired.')
				}
				return
			}

			try {
				const data = await apiFetchCurrentTerms(token)
				if (!cancelled) {
					setTermsData(data)
				}
			} catch (e) {
				if (!cancelled) {
					setTermsData(null)
					setTermsError(e instanceof Error ? e.message : 'Could not load terms.')
				}
			} finally {
				if (!cancelled) {
					setTermsLoading(false)
				}
			}
		})()

		return () => {
			cancelled = true
		}
	}, [termsOpen, getValidAccessToken])

	const closeTerms = () => {
		setTermsOpen(false)
		setTermsError(null)
	}

	if (isMobile) return <MobilePage />

	return (
		<div className={cn('tw-container', styles.page)}>
			<h1 className={styles.title}>{title}</h1>

			<div className={cn(styles.content, { ['!grid-cols-3']: isCarrier })}>
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
								{isCarrier ? (
									'Carrier'
								) : (
									<>{`${accountType?.charAt(0).toUpperCase()}${accountType?.slice(1)}`}</>
								)}
							</div>
						</div>

						<div className={styles.hr} />

						{(!!user?.company_name ||
							!!user?.company_registration_number ||
							!!user?.company_address) && (
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
						)}

						<div className={styles.item}>
							<div className={styles.label}>Contacts</div>
							<div className={styles.value}>{user?.phone || EMPTY_STRING}</div>
						</div>

						<div className={styles.item}>
							<div className={styles.label}>E-mail</div>
							<div className={styles.value}>{user?.email || EMPTY_STRING}</div>
						</div>

						<div className={styles.hr} />

						<button type="button" className={styles.termsLink} onClick={() => setTermsOpen(true)}>
							Terms & Conditions
						</button>
					</div>
				</div>

				{!isCarrier && (
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

				{isCarrier && (
					<>
						<div className={styles.centerWrapper}>
							<div className={cn(styles.center, styles.top)}>
								<div className={styles.title}>My stats</div>

								<div className={styles.statsWrapper}>
									<div>
										<CircleChart
											size={120}
											percent={orders.delivery_percent ?? 0}
											title={`${orders.delivery_percent ?? 0}%`}
											subtitle="Successful deliveries"
										/>
									</div>

									<div>
										<CircleChart
											size={150}
											percent={orders.approval_percent ?? 0}
											title={`${orders.approval_percent ?? 0}%`}
											subtitle="Apply rate"
										/>
									</div>
								</div>
							</div>

							<div className={cn(styles.center, styles.bottom)}>
								<div className={styles.title}>My orders</div>

								<div className={styles.orders}>
									<div className={styles.order}>
										<div className={styles.value}>{orders.total || 0}</div>
										<div className={styles.label}>Total orders</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.delivered || 0}</div>
										<div className={styles.label}>Completed</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.cancelled || 0}</div>
										<div className={styles.label}>Cancelled</div>
									</div>
									<div className={styles.order}>
										<div className={styles.value}>{orders.in_progress || 0}</div>
										<div className={styles.label}>In progress</div>
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

			<Modal isOpen={termsOpen} onClose={closeTerms} maxWidth="min(92vw, 720px)">
				<div className={styles.termsModal}>
					<div className={styles.termsModalHeader}>
						<h2 className={styles.termsModalTitle}>
							{termsData?.title?.trim() ? termsData.title : 'Terms & Conditions'}
						</h2>
						{termsData?.subtitle ? <p className={styles.termsModalSubtitle}>{termsData.subtitle}</p> : null}
						{termsData ? (
							<div className={styles.termsModalMeta}>
								Version {termsData.version}
								{termsData.publishedAt ? ` · ${new Date(termsData.publishedAt).toLocaleDateString()}` : ''}
							</div>
						) : null}
					</div>

					<div className={styles.termsModalScroll}>
						{termsLoading ? <p className={styles.termsModalMessage}>Loading…</p> : null}
						{termsError ? <p className={styles.termsModalError}>{termsError}</p> : null}
						{!termsLoading && !termsError && termsData ? (
							<div
								className={styles.termsModalHtml}
								// Trusted HTML from Sonata (admin-only). Do not pass user-generated content here.
								dangerouslySetInnerHTML={{ __html: termsData.html }}
							/>
						) : null}
					</div>
				</div>
			</Modal>
		</div>
	)
}
