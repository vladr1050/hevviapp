import { type FC, useEffect, useState } from 'react'
import { Controller, SubmitHandler, useForm } from 'react-hook-form'

import { apiFetchPublicTermsCurrent, type TermsCurrentResponse } from '@api/termsApi'
import { apiLogin, apiResetPassword } from '@api/authApi'
import { saveTokens } from '@hooks/useAuth'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { Icon } from '@ui/Icon/Icon'
import { Input } from '@ui/Input/Input'
import { Modal } from '@ui/Modal/Modal'
import { Tabs } from '@ui/Tabs/Tabs'
import { cn } from '@utils/cn'

import styles from './Login.module.css'

import { MobilePage } from '../MobilePage/MobilePage'
import profileStyles from '../Profile/Profile.module.css'
import registrationStyles from '../Registration/Registration.module.css'

import { type LoginFormValues, resolver } from './login.schema'

interface LoginProps {
	device?: DeviceType
}

export const LoginPage: FC<LoginProps> = ({ device }) => {
	const { isMobile } = useDevice(device)

	const [isReset, setIsReset] = useState(false)
	const [isLoading, setIsLoading] = useState(false)
	const [error, setError] = useState<string | null>(null)

	const [termsOpen, setTermsOpen] = useState(false)
	const [termsLoading, setTermsLoading] = useState(false)
	const [termsError, setTermsError] = useState<string | null>(null)
	const [termsData, setTermsData] = useState<TermsCurrentResponse | null>(null)

	const {
		control,
		handleSubmit,
		watch,
		trigger,
		formState: { errors },
	} = useForm<LoginFormValues>({
		resolver,
		defaultValues: {
			portalType: 'Sender',
			termsAccepted: false,
			login: '',
			password: '',
		},
	})

	const portalType = watch('portalType')
	const termsAccepted = watch('termsAccepted')

	useEffect(() => {
		if (!termsOpen) {
			return
		}

		const audience = portalType === 'Carrier' ? 'carrier' : 'sender'
		let cancelled = false

		;(async () => {
			setTermsLoading(true)
			setTermsError(null)
			setTermsData(null)

			try {
				const data = await apiFetchPublicTermsCurrent(audience)
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
	}, [termsOpen, portalType])

	const closeTerms = () => {
		setTermsOpen(false)
		setTermsError(null)
	}

	const onSubmit: SubmitHandler<LoginFormValues> = async (values, event) => {
		const submitter = (event?.nativeEvent as SubmitEvent)?.submitter as HTMLButtonElement | null

		if (submitter?.name === 'reset') {
			setIsReset(true)
			return
		}

		if (!values.termsAccepted) {
			return
		}

		setError(null)
		setIsLoading(true)

		try {
			const result = await apiLogin(values.login, values.password)

			saveTokens(result.access_token, result.refresh_token, result.expires_in, result.user)

			window.location.href =
				result.account_type === 'carrier' ? '/carrier/requests' : '/user/requests'
		} catch (err: unknown) {
			const message = err instanceof Error ? err.message : 'Login failed'
			setError(message)
		} finally {
			setIsLoading(false)
		}
	}

	const onReset = async () => {
		const valid = await trigger('login')

		if (!valid) return

		setIsReset(true)

		await apiResetPassword(watch('login'))
	}

	if (isMobile) return <MobilePage />

	return (
		<div className={cn('tw-container', styles.page)}>
			<div className={cn(styles.content, { [styles.reset]: isReset })}>
				<div className={styles.left}>
					{!isReset && (
						<h1>
							Heavy cargo.
							<br />
							Delivered fast.
						</h1>
					)}
				</div>

				<form className={styles.right} onSubmit={handleSubmit(onSubmit)}>
					{!isReset && (
						<div className={registrationStyles.titleWrapper}>
							<h1 className={registrationStyles.title}>Login</h1>

							<Controller
								control={control}
								name="portalType"
								render={({ field: { value, onChange } }) => (
									<Tabs
										className={registrationStyles.tabs}
										classNames={{ tab: '!w-[140px]' }}
										defaultValue={value}
										items={[
											{
												label: (
													<div className={registrationStyles.item}>
														{portalType === 'Sender' && (
															<div className={registrationStyles.iconWrapper}>
																<Icon type="box" size={20} />
															</div>
														)}
														Sender
													</div>
												),
												value: 'Sender',
											},
											{
												label: (
													<div className={registrationStyles.item}>
														{portalType === 'Carrier' && (
															<div className={registrationStyles.iconWrapper}>
																<Icon type="vehicle" size={20} />
															</div>
														)}
														Carrier
													</div>
												),
												value: 'Carrier',
											},
										]}
										onChange={(v) => onChange(v as LoginFormValues['portalType'])}
									/>
								)}
							/>
						</div>
					)}

					{isReset ? (
						<h1 className={styles.titleFallback}>Recover password</h1>
					) : null}

					{isReset ? (
						<div className="flex flex-col items-center gap-9 animate-fade">
							<div className="bg-primary rounded-full w-[120px] h-[120px] flex items-center justify-center text-black">
								<Icon type="block_mail" size={60} />
							</div>

							<div className="text-center font-medium text-base">
								Recovery email was sent to:
								<br />
								<span className="text-center font-medium text-base">{watch('login')}</span>
							</div>
						</div>
					) : (
						<div className={styles.inputs}>
							<Input
								control={control}
								name="login"
								placeholder="E-mail"
								label="E-mail"
								type="email"
								required
								error={errors?.login?.message}
							/>

							<div className="">
								<Input
									control={control}
									name="password"
									placeholder="Password"
									label="Password"
									type="password"
									error={errors?.password?.message}
								/>

								<button type="button" className={styles.resetPassword} onClick={onReset}>
									Reset password
								</button>
							</div>

							{error && <p className="text-red-500 text-sm text-center">{error}</p>}
						</div>
					)}

					{isReset ? (
						<Button
							key="back"
							type="button"
							name="login"
							className="w-full h-12"
							disabled={isLoading}
							onClick={() => setIsReset(false)}
						>
							Back to login
						</Button>
					) : (
						<div className="w-full flex flex-col gap-6">
							<div className={styles.agreementBlock}>
								<Controller
									control={control}
									name="termsAccepted"
									render={({ field: { value, onChange } }) => (
										<Checkbox value={value} onChange={onChange} alignTop>
											<span className={styles.agreementText}>
												Piekrītu{' '}
												<button
													type="button"
													className={styles.termsDocLink}
													onClick={(e) => {
														e.preventDefault()
														e.stopPropagation()
														setTermsOpen(true)
													}}
												>
													Hevvi.app platformas lietošanas noteikumiem un privātuma politikai
												</button>
												.
											</span>
										</Checkbox>
									)}
								/>
							</div>

							<Button
								key="login"
								type="submit"
								name="login"
								className="w-full h-12"
								disabled={isLoading || !termsAccepted}
							>
								{isLoading ? 'Loading...' : 'Login'}
							</Button>
						</div>
					)}
				</form>
			</div>

			<Modal isOpen={termsOpen} onClose={closeTerms} maxWidth="min(92vw, 720px)">
				<div className={profileStyles.termsModal}>
					<div className={profileStyles.termsModalHeader}>
						<h2 className={profileStyles.termsModalTitle}>
							{termsData?.title?.trim() ? termsData.title : 'Terms & Conditions'}
						</h2>
						{termsData?.subtitle ? (
							<p className={profileStyles.termsModalSubtitle}>{termsData.subtitle}</p>
						) : null}
						{termsData ? (
							<div className={profileStyles.termsModalMeta}>
								Version {termsData.version}
								{termsData.publishedAt
									? ` · ${new Date(termsData.publishedAt).toLocaleDateString()}`
									: ''}
							</div>
						) : null}
					</div>

					<div className={profileStyles.termsModalScroll}>
						{termsLoading ? <p className={profileStyles.termsModalMessage}>Loading…</p> : null}
						{termsError ? <p className={profileStyles.termsModalError}>{termsError}</p> : null}
						{!termsLoading && !termsError && termsData ? (
							<div
								className={profileStyles.termsModalHtml}
								// Trusted HTML from Sonata (admin-only).
								dangerouslySetInnerHTML={{ __html: termsData.html }}
							/>
						) : null}
					</div>
				</div>
			</Modal>
		</div>
	)
}
