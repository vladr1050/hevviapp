import { type FC, useEffect, useState } from 'react'

import { WaitingListForm } from '@components/WaitingListForm/WaitingListForm'
import { apiFetchPublicTermsCurrent, type TermsCurrentResponse } from '@api/termsApi'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Button } from '@ui/Button/Button'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'

import { ProductCarousel } from './components/ProductCarousel'
import { landingAssets } from './landingAssets'
import styles from './Landing.module.css'

interface LandingProps {
	device?: DeviceType
}

const scrollToEarlyAccess = () => {
	document.getElementById('early-access')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

export const LandingPage: FC<LandingProps> = ({ device }) => {
	const { isMobile } = useDevice(device)
	const [termsOpen, setTermsOpen] = useState(false)
	const [termsLoading, setTermsLoading] = useState(false)
	const [termsError, setTermsError] = useState<string | null>(null)
	const [termsData, setTermsData] = useState<TermsCurrentResponse | null>(null)

	useEffect(() => {
		if (!termsOpen) {
			return
		}

		let cancelled = false

		;(async () => {
			setTermsLoading(true)
			setTermsError(null)
			setTermsData(null)

			try {
				const data = await apiFetchPublicTermsCurrent('sender')
				if (!cancelled) {
					setTermsData(data)
				}
			} catch (e) {
				if (!cancelled) {
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
	}, [termsOpen])

	return (
		<div className={cn(styles.page, { [styles.mobile]: isMobile })}>
			<img src={landingAssets.decoLine1} alt="" className={styles.decoLine1} aria-hidden="true" />
			<img src={landingAssets.decoLine2} alt="" className={styles.decoLine2} aria-hidden="true" />
			<img src={landingAssets.decoLine3} alt="" className={styles.decoLine3} aria-hidden="true" />

			<header className={styles.header}>
				<div className={cn('tw-container', styles.headerInner)}>
					<a href="/" className={styles.logoLink} aria-label="Hevvi home">
						<img src={landingAssets.logo} alt="Hevvi" className={styles.logo} />
					</a>

					<a href="/login" className={styles.loginCluster} aria-label="Login">
						<img src={landingAssets.loginCluster} alt="Login" />
					</a>
				</div>
			</header>

			<main>
				<section className={styles.hero}>
					<div className={cn('tw-container', styles.heroInner)}>
						<div className={styles.heroCopy}>
							<h1 className={styles.heroTitle}>
								Heavy cargo,
								<br />
								shipped in 48 hours.
							</h1>
							<p className={styles.heroSubtitle}>
								Order 100kg+ cargo anywhere in Latvia — as easy as booking a taxi ride.
							</p>
							<Button type="button" className={styles.heroCta} onClick={scrollToEarlyAccess}>
								Get early access
							</Button>
						</div>

						<div className={styles.heroVisual}>
							<img
								src={landingAssets.heroComposite}
								alt=""
								className={styles.heroComposite}
								loading="eager"
							/>
						</div>
					</div>
				</section>

				<ProductCarousel />

				<section id="early-access" className={styles.registration}>
					<div className={cn('tw-container', styles.registrationInner)}>
						<div className={styles.registrationCopy}>
							<h2>Claim your early access</h2>
							<p>
								We are onboarding early users and testing the platform with real shipments in
								Latvia. Apply below and we&apos;ll be in touch.
							</p>
						</div>

						<div className={styles.registrationForm}>
							<WaitingListForm variant="landing" />
						</div>
					</div>
				</section>
			</main>

			<footer className={styles.footer}>
				<div className={cn('tw-container', styles.footerInner)}>
					<div className={styles.footerBrand}>
						<img src={landingAssets.logo} alt="Hevvi" className={styles.footerLogo} />
						<span className={styles.footerBeta}>beta</span>
					</div>
					<div className={styles.footerLinks}>
						<a href="mailto:support@hevvi.app">support@hevvi.app</a>
						<a href="tel:+37126166597">+371 26166597</a>
						<span>Hevvi Operations Team</span>
					</div>
					<button type="button" className={styles.footerTerms} onClick={() => setTermsOpen(true)}>
						Terms &amp; Conditions
					</button>
				</div>
			</footer>

			<Modal isOpen={termsOpen} onClose={() => setTermsOpen(false)} maxWidth="min(92vw, 720px)">
				<div className={styles.termsModal}>
					<h2>{termsData?.title?.trim() ? termsData.title : 'Terms & Conditions'}</h2>
					{termsLoading && <p>Loading…</p>}
					{termsError && <p className={styles.termsError}>{termsError}</p>}
					{!termsLoading && !termsError && termsData?.html ? (
						<div
							className={styles.termsBody}
							dangerouslySetInnerHTML={{ __html: termsData.html }}
						/>
					) : null}
				</div>
			</Modal>
		</div>
	)
}
