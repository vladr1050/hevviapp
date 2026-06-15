import { type FC, useState } from 'react'
import { Controller, SubmitHandler, useForm } from 'react-hook-form'

import { apiJoinWaitingList } from '@api/waitingListApi'
import { AccountType } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Button } from '@ui/Button/Button'
import { Icon } from '@ui/Icon/Icon'
import { Input } from '@ui/Input/Input'
import { Tabs } from '@ui/Tabs/Tabs'
import { cn } from '@utils/cn'

import styles from './Registration.module.css'

interface RegistrationProps {
	device?: DeviceType
}

type FormValues = {
	type: AccountType
	email: string
	company_website: string
}

export const RegistrationPage: FC<RegistrationProps> = ({ device }) => {
	const { isMobile } = useDevice(device)
	const [isSuccess, setIsSuccess] = useState(false)
	const [submittedEmail, setSubmittedEmail] = useState('')
	const [isLoading, setIsLoading] = useState(false)
	const [error, setError] = useState<string | null>(null)

	const { handleSubmit, control, watch, register } = useForm<FormValues>({
		defaultValues: { type: 'Sender', email: '', company_website: '' },
	})

	const accountType = watch('type')

	const onSubmit: SubmitHandler<FormValues> = async (values) => {
		setError(null)
		setIsLoading(true)

		try {
			const type = values.type === 'Carrier' ? 'carrier' : 'sender'
			await apiJoinWaitingList(values.email, type, values.company_website)
			setSubmittedEmail(values.email.trim())
			setIsSuccess(true)
		} catch (err: unknown) {
			const message = err instanceof Error ? err.message : 'Registration failed'
			setError(message)
		} finally {
			setIsLoading(false)
		}
	}

	return (
		<div className={cn('tw-container', styles.page, { [styles.mobile]: isMobile })}>
			<div className={styles.content}>
				<div
					className={cn(styles.left, {
						[styles.sender]: accountType === 'Sender',
						[styles.carrier]: accountType === 'Carrier',
						[styles.mobileLeft]: isMobile,
					})}
				>
					<div className={styles.info}>
						{accountType === 'Sender' ? (
							<h2>
								Heavy
								<br />
								cargo delivery
							</h2>
						) : (
							<h2>
								Get more
								<br />
								cargo orders
							</h2>
						)}

						<div className={styles.infoWrapper}>
							<div className={styles.item}>
								<div className={styles.iconWrapper}>
									<Icon
										type={accountType === 'Sender' ? 'time' : 'confirm_order'}
										size={34}
									/>
								</div>

								{accountType === 'Sender' ? (
									<span>
										Order
										<br />
										in seconds
									</span>
								) : (
									<span>
										Predictable
										<br />
										payments
									</span>
								)}
							</div>

							<div className={styles.item}>
								<div className={styles.iconWrapper}>
									<Icon
										type={accountType === 'Sender' ? 'vehicle_drive' : 'path_map'}
										size={34}
									/>
								</div>

								{accountType === 'Sender' ? (
									<span>
										Get in
										<br />
										48 hours
									</span>
								) : (
									<span>
										More loaded
										<br />
										miles
									</span>
								)}
							</div>
						</div>
					</div>
				</div>

				<div className={cn(styles.right, { [styles.success]: isSuccess })}>
					{isSuccess ? (
						<div className={styles.successContent}>
							<h1 className={styles.successTitle}>Awesome! We&apos;ve registered</h1>

							<div className={styles.successIcon}>
								<Icon type="profile" size={60} />
							</div>

							<p className={styles.successEmail}>{submittedEmail}</p>
							<p className={styles.successHint}>We&apos;ll get in touch with you!</p>

							<Button
								type="button"
								className="w-full"
								onClick={() => {
									window.location.href = '/login'
								}}
							>
								Back to login
							</Button>
						</div>
					) : (
						<form className={styles.form} onSubmit={handleSubmit(onSubmit)}>
							<div className={styles.titleWrapper}>
								<h1 className={styles.title}>Join waiting list as</h1>

								<Controller
									control={control}
									name="type"
									render={({ field: { value, onChange } }) => (
										<Tabs
											className={styles.tabs}
											classNames={{ tab: '!w-[140px]' }}
											defaultValue={value}
											items={[
												{
													label: (
														<div className={styles.tabItem}>
															{value === 'Sender' && (
																<div className={styles.tabIcon}>
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
														<div className={styles.tabItem}>
															{value === 'Carrier' && (
																<div className={styles.tabIcon}>
																	<Icon type="vehicle" size={20} />
																</div>
															)}
															Carrier
														</div>
													),
													value: 'Carrier',
												},
											]}
											onChange={onChange}
										/>
									)}
								/>
							</div>

							<div className={styles.inputs}>
								<Input
									control={control}
									name="email"
									placeholder="janis.doe@gmail.com"
									label="E-mail"
									type="email"
									required
								/>

								{/* Honeypot — hidden from users, filled by bots */}
								<input
									type="text"
									tabIndex={-1}
									autoComplete="off"
									aria-hidden="true"
									className={styles.honeypot}
									{...register('company_website')}
								/>
							</div>

							{error && <p className={styles.error}>{error}</p>}

							<Button type="submit" className="w-full" disabled={isLoading}>
								{isLoading ? 'Submitting…' : 'Join waiting list'}
							</Button>
						</form>
					)}
				</div>
			</div>
		</div>
	)
}
