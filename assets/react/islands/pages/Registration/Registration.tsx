import { type FC, useEffect, useState } from 'react'
import { Controller, SubmitHandler, useForm } from 'react-hook-form'

import { AccountType } from '@config/constants'
import { DeviceType, useDevice } from '@hooks/useDevice'
import { Button } from '@ui/Button/Button'
import { Checkbox } from '@ui/Checkbox/Checkbox'
import { Icon } from '@ui/Icon/Icon'
import { Input } from '@ui/Input/Input'
import { Tabs } from '@ui/Tabs/Tabs'
import { cn } from '@utils/cn'

import styles from './Registration.module.css'

import { MobilePage } from '../MobilePage/MobilePage'

interface RegistrationProps {
	device?: DeviceType
}

type FormValues = {
	type: AccountType
	login: string
	password: string
	repeat_password: string
	checkbox: boolean
}

export const RegistrationPage: FC<RegistrationProps> = ({ device }) => {
	const { isMobile } = useDevice(device)

	const {
		handleSubmit,
		control,
		watch,
		formState: { errors },
		setError,
		clearErrors,
	} = useForm<FormValues>({ defaultValues: { type: 'Sender' } })

	useEffect(() => {
		clearErrors('password')
		clearErrors('repeat_password')
	}, [watch('password'), watch('repeat_password')])

	const onSubmit: SubmitHandler<FormValues> = async (values) => {
		if (values.password.length < 8)
			return setError('password', { message: 'Password must be at least 8 characters long' })

		if (values.password !== values.repeat_password)
			return setError('repeat_password', { message: 'Passwords do not match' })
	}

	if (isMobile) return <MobilePage />

	return (
		<div className={cn('tw-container', styles.page)}>
			<div className={styles.content}>
				<div
					className={cn(styles.left, {
						[styles.sender]: watch('type') === 'Sender',
						[styles.carrier]: watch('type') === 'Carrier',
					})}
				>
					<div className={styles.info}>
						{watch('type') === 'Sender' ? (
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
									<Icon type={watch('type') === 'Sender' ? 'time' : 'confirm_order'} size={34} />
								</div>

								{watch('type') === 'Sender' ? (
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
										type={watch('type') === 'Sender' ? 'vehicle_drive' : 'path_map'}
										size={34}
									/>
								</div>

								{watch('type') === 'Sender' ? (
									<span>
										Get in
										<br />
										48 hours
									</span>
								) : (
									<span>
										Reduce
										<br />
										empty miles
									</span>
								)}
							</div>
						</div>
					</div>
				</div>

				<form className={styles.right} onSubmit={handleSubmit(onSubmit)}>
					<div className={styles.titleWrapper}>
						<h1 className={styles.title}>Register as</h1>

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
												<div className={styles.item}>
													{watch('type') === 'Sender' && (
														<div className={styles.iconWrapper}>
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
												<div className={styles.item}>
													{watch('type') === 'Carrier' && (
														<div className={styles.iconWrapper}>
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
						<Input control={control} name="login" placeholder="Your E-mail" type="email" required />

						<Input
							control={control}
							name="password"
							placeholder="New password"
							type="password"
							required
							error={errors.password?.message || !!errors.repeat_password}
						/>

						<div>
							<Input
								control={control}
								name="repeat_password"
								placeholder="Repeat password"
								type="password"
								required
								error={errors.repeat_password?.message}
							/>

							<span className={styles.info}>Password (must incl. at least 8 characters)</span>
						</div>
					</div>

					<div className="w-full flex flex-col gap-6">
						<Controller
							control={control}
							name="checkbox"
							rules={{ required: true }}
							render={({ field: { value, onChange } }) => (
								<Checkbox
									defaultChecked={value}
									onChange={onChange}
									className={cn({ ['text-red-500']: errors.checkbox })}
								>
									I agree to SIA Hevvi Terms & Conditions, Privacy Policy, Communication material
									policy
								</Checkbox>
							)}
						/>

						<Button type="submit" className="w-full">
							Register
						</Button>
					</div>
				</form>
			</div>
		</div>
	)
}
