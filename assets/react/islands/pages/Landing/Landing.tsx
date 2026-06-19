import { type FC, useEffect, useState } from 'react'

import { WaitingListForm } from '@components/WaitingListForm/WaitingListForm'
import { apiFetchPublicTermsCurrent, type TermsCurrentResponse } from '@api/termsApi'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Button } from '@ui/Button/Button'
import { Modal } from '@ui/Modal/Modal'
import { cn } from '@utils/cn'

import { HeroVisual } from './components/HeroVisual'
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
			<main className={styles.main}>
				<section className={styles.heroSection}>
					<div className={styles.heroShell}>
						<header className={styles.heroHeader}>
							<a href="/" className={styles.logoLink} aria-label="Hevvi home">
								<img src={landingAssets.logo} alt="Hevvi" className={styles.logo} width={71} height={19} />
								<span className={styles.betaBadge}>beta</span>
							</a>

							<div className={styles.heroNav}>
								<a href="tel:+37126166597" className={styles.heroContact}>
									+371 26166597
								</a>
								<a href="mailto:support@hevvi.app" className={styles.heroContact}>
									support@hevvi.app
								</a>
								<a href="/login" className={styles.loginLink} aria-label="Login">
									<span>Login</span>
									<img src={landingAssets.loginIcon} alt="" className={styles.loginIcon} width={32} height={32} />
								</a>
							</div>
						</header>

						<div className={styles.heroCard}>
							<div className={styles.heroBody}>
								<div className={styles.heroCopy}>
									<h1 className={styles.heroTitle}>
										Heavy cargo,
										<br />
										shipped in 48 hours.
									</h1>
									<p className={styles.heroSubtitle}>
										Order 100kg+ cargo anywhere in Latvia - as easy as booking a taxi ride.
									</p>
									<Button type="button" className={styles.heroCta} onClick={scrollToEarlyAccess}>
										Get early access
									</Button>
								</div>

								<img
									src={landingAssets.heroFeatures}
									alt=""
									className={styles.heroFeatures}
									width={472}
									height={48}
									loading="eager"
									aria-hidden="true"
								/>

								<div className={styles.heroVisual}>
									<HeroVisual />
								</div>
							</div>
						</div>
					</div>
				</section>

				<ProductCarousel />

				<section id="early-access" className={styles.registration}>
					<div className={styles.registrationWrap}>
						<div className={styles.registrationHeader}>
							<h2>Claim your early access</h2>
							<p>
								We are onboarding early users and testing the platform with real shipments in
								Latvia. Apply below and we&apos;ll be in touch.
							</p>
						</div>

						<div className={styles.registrationPanel}>
							<div className={styles.registrationBanner}>
								<img
									src={landingAssets.registrationTrucks}
									alt=""
									className={styles.registrationImage}
									width={708}
									height={514}
									loading="lazy"
								/>
							</div>
							<div className={styles.registrationFormCard}>
								<WaitingListForm variant="landing" />
							</div>
						</div>
					</div>
				</section>
			</main>

			<footer className={styles.footer}>
				<div className={styles.footerInner}>
					<div className={styles.footerBrand}>
						<img src={landingAssets.logoFooter} alt="Hevvi" className={styles.footerLogo} width={75} height={19} />
						<span className={styles.footerTeam}>Hevvi Operations Team</span>
					</div>
					<div className={styles.footerLinks}>
						<a href="tel:+37126166597">+371 26166597</a>
						<a href="mailto:support@hevvi.app">support@hevvi.app</a>
						<button type="button" className={styles.footerTerms} onClick={() => setTermsOpen(true)}>
							Terms &amp; Conditions
						</button>
					</div>
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
